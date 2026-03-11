import mysql.connector
import sys
from datetime import datetime

# Function to connect to databases
def create_connection(db_name):
    try:
        conn = mysql.connector.connect(
            user='root',
            password='',     # Default XAMPP password
            host='localhost',
            database=db_name
        )
        print(f"Connected to {db_name}")
        return conn
    except mysql.connector.Error as e:
        print(f"Error connecting to {db_name}: {e}")
        sys.exit(1)

def etl_process():
    db1 = create_connection('4990_db1')
    db2 = create_connection('4990_db2')
    dw = create_connection('4990_warehouse')
    source_dbs = {1: db1, 2: db2} 
    dw_cursor = dw.cursor()

    # Warehouse Reset
    print("\nRESETTING WAREHOUSE")
    # Turn off Foreign Key checks so we don't get errors
    dw_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # Reset Tables
    for table in ['FactSales', 'FactInventory', 'CustomerDim', 'ProductDim', 'DatetimeDim', 'BranchDim']:
        dw_cursor.execute(f"TRUNCATE TABLE {table}")
        
    # Turn safety checks back on
    dw_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    dw.commit() # Save changes

    # BranchDim data doesn't come from the source databases, so we manually insert it
    print("LOADING BRANCH DIMENSION")
    dw_cursor.executemany(
        "INSERT INTO BranchDim (BranchKey, City, Province, Country) VALUES (%s, %s, %s, %s)",
        [
            (1, 'Windsor', 'Ontario', 'Canada'),
            (2, 'Toronto', 'Ontario', 'Canada')
        ]
    )
    dw.commit()

    # Mapping to deal with duplicate CustomerId
    cust_map = {1: {}, 2: {}} 
    prod_map = {1: {}, 2: {}} 
    date_map = {}             

    # Load customers
    print("LOADING CUSTOMERS")
    # Loop through DB1 and DB2
    for branch_id, db_conn in source_dbs.items():
        cursor = db_conn.cursor()
        cursor.execute("SELECT CustomerID, CustomerName, Country, Phone, Email FROM Customers")
        for cid, cname, country, phone, email in cursor.fetchall():
            # Insert into the Warehouse
            dw_cursor.execute(
                "INSERT INTO CustomerDim (SourceCustomerID, CustomerName, Phone, Email, Country) VALUES (%s, %s, %s, %s, %s)",
                (cid, cname, phone, email, country)
            )
            # dw_cursor.lastrowid gets the new Auto-Increment Key, we use it later when building the fact table
            cust_map[branch_id][cid] = dw_cursor.lastrowid
        cursor.close()
    dw.commit()

    # Load Products
    print("LOADING PRODUCTS")
    for branch_id, db_conn in source_dbs.items():
        cursor = db_conn.cursor()
        cursor.execute("SELECT ProductID, ProductName, Category, CurrentPrice FROM Products")
        
        for pid, pname, category, price in cursor.fetchall():
            dw_cursor.execute(
                "INSERT INTO ProductDim (SourceProductID, ProductName, Category, CurrentPrice) VALUES (%s, %s, %s, %s)",
                (pid, pname, category, price)
            )
            # Map the old ProductID to the new ProductKey
            prod_map[branch_id][pid] = dw_cursor.lastrowid
        cursor.close()
    dw.commit()

    # Load DateTime
    print("LOADING DATETIME DIMENSION")
    raw_dates = set() # A set prevents duplicate dates from being added
    
    # Extract dates from databases (Orders and Stock table)
    for branch_id, db_conn in source_dbs.items():
        cursor = db_conn.cursor()
        
        cursor.execute("SELECT DISTINCT OrderDateTime FROM Orders WHERE OrderDateTime IS NOT NULL")
        raw_dates.update([row[0] for row in cursor.fetchall()])
        
        cursor.execute("SELECT DISTINCT LastRestocked FROM Stock WHERE LastRestocked IS NOT NULL")
        raw_dates.update([row[0] for row in cursor.fetchall()])
        cursor.close()

    # Sort them in chronological order
    sorted_dates = sorted(list(raw_dates))
    
    # Transform the timestamp down into its parts
    for dt in sorted_dates:
        full_date = dt.date()
        sale_time = dt.time()
        month = dt.month
        # Calculate Quarter (e.g., Month 5: (5-1)//3 + 1 = 2nd Quarter)
        quarter = (month - 1) // 3 + 1 
        year = dt.year

        # Insert into DatetimeDim
        dw_cursor.execute(
            """INSERT INTO DatetimeDim (FullDate, SaleTime, Month, Quarter, Year) 
               VALUES (%s, %s, %s, %s, %s)""",
            (full_date, sale_time, month, quarter, year)
        )
        # Catch the new DateKey instantly and map it to the basic string version of the date
        date_map[str(dt)] = dw_cursor.lastrowid
        
    dw.commit()

    # Build Fact Sales
    print("LOADING FACT SALES")
    for branch_id, db_conn in source_dbs.items():
        cursor = db_conn.cursor()
        
        # Matching Orders to Order_Items
        query = """
            SELECT o.OrderID, o.CustomerID, o.OrderDateTime, oi.ProductID, oi.Quantity, oi.UnitPrice 
            FROM Orders o
            JOIN Order_Items oi ON o.OrderID = oi.OrderID
        """
        cursor.execute(query)
        for order_id, cid, order_dt, pid, qty, unit_price in cursor.fetchall():
            # Look up the new Warehouse Keys
            prod_key = prod_map[branch_id].get(pid)
            cust_key = cust_map[branch_id].get(cid)
            
            # Use the basic string for a guaranteed match
            date_key = date_map.get(str(order_dt))
            
            # Safety check
            if date_key is None:
                print(f"Warning: Could not find DateKey for {order_dt}. Skipping Order {order_id}.")
                continue
            
            # Calculate the total money made
            revenue = float(qty) * float(unit_price)

            # Load all the Keys and math into Fact table
            dw_cursor.execute(
                """INSERT INTO FactSales 
                   (ProductKey, CustomerKey, DateKey, BranchKey, SourceOrderID, Quantity, UnitPrice, Revenue) 
                   VALUES (%s, %s, %s, %s, %s, %s, %s, %s)""",
                (prod_key, cust_key, date_key, branch_id, order_id, qty, unit_price, revenue)
            )      
        cursor.close()
    dw.commit()

    # Build Fact Inventory
    print("LOADING FACT INVENTORY")
    for branch_id, db_conn in source_dbs.items():
        cursor = db_conn.cursor()
        
        # Grab stock levels
        cursor.execute("SELECT ProductID, CurrentStock, RestockThreshold, LastRestocked FROM Stock")
        for pid, current_stock, threshold, last_restocked in cursor.fetchall():
            # Look up the Warehouse Key for the product
            prod_key = prod_map[branch_id].get(pid)
            
            # Use the basic string (if it exists)
            date_key = date_map.get(str(last_restocked)) if last_restocked else None

            # Skip if the date is missing to prevent database crashes
            if date_key is None and last_restocked is not None:
                print(f"Warning: Could not find DateKey for Inventory {pid}.")
                continue

            # Load into FactInventory
            dw_cursor.execute(
                """INSERT INTO FactInventory 
                   (ProductKey, DateKey, BranchKey, CurrentStock, RestockThreshold) 
                   VALUES (%s, %s, %s, %s, %s)""",
                (prod_key, date_key, branch_id, current_stock, threshold)
            )
        cursor.close()
    dw.commit()

    dw_cursor.close()
    db1.close()
    db2.close()
    dw.close()

    print("\nETL DONE")

if __name__ == '__main__':
    etl_process()
