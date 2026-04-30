import csv

# File paths
CONTACTS_FILE = 'contacts_fixed.csv'
DISCUSSIONS_FILE = 'discussion_log.csv'
OUTPUT_FILE = 'discussion_log_with_company.csv'

# Load contacts: map id to company name
id_to_company = {}
with open(CONTACTS_FILE, newline='', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        contact_id = row.get('id')
        company = row.get('company', '')
        if contact_id:
            id_to_company[contact_id] = company

# Read discussion log and add company name by contact_id
with open(DISCUSSIONS_FILE, newline='', encoding='utf-8') as f_in, \
     open(OUTPUT_FILE, 'w', newline='', encoding='utf-8') as f_out:
    reader = csv.DictReader(f_in)
    fieldnames = reader.fieldnames + ['company']
    writer = csv.DictWriter(f_out, fieldnames=fieldnames)
    writer.writeheader()
    for row in reader:
        contact_id = row.get('contact_id')
        row['company'] = id_to_company.get(contact_id, '')
        writer.writerow(row)

print(f"Done. Output written to {OUTPUT_FILE}")
