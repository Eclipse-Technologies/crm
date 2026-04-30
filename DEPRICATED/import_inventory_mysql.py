import csv
import pymysql

# MySQL connection settings
conn = pymysql.connect(host='localhost', user='admin', password='M@sonnotte032', database='crmdb')
cursor = conn.cursor()

with open('inventory.csv', newline='', encoding='utf-8') as csvfile:
    reader = csv.DictReader(csvfile)
    for row in reader:
        fields = [
            'item_id','item_name','description','category','brand','model','serial_number','barcode','rfid_tag',
            'supplier_id','supplier_name','purchase_date','cost_price','margin','selling_price','currency',
            'quantity_in_stock','reorder_level','reorder_quantity','unit','warehouse','location','status',
            'created_at','updated_at','created_by','updated_by','notes'
        ]
        values = [row.get(f, None) or None for f in fields]
        placeholders = ','.join(['%s'] * len(fields))
        sql = f"REPLACE INTO inventory ({','.join(fields)}) VALUES ({placeholders})"
        cursor.execute(sql, values)

conn.commit()
cursor.close()
conn.close()
print('Inventory import complete.')
