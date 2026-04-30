import csv

# File paths
CONTACTS_FILE = 'contacts_fixed.csv'
DISCUSSIONS_FILE = 'discussion_log.csv'
OUTPUT_FILE = 'discussion_log_with_company_id.csv'

# Load contacts: map company name (lowercase, stripped) to id
company_to_id = {}
with open(CONTACTS_FILE, newline='', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        company = (row.get('company') or '').strip().lower()
        contact_id = row.get('id')
        if company and contact_id:
            company_to_id[company] = contact_id

# Read discussion log and add matching contact id by company
with open(DISCUSSIONS_FILE, newline='', encoding='utf-8') as f_in, \
     open(OUTPUT_FILE, 'w', newline='', encoding='utf-8') as f_out:
    reader = csv.DictReader(f_in)
    fieldnames = reader.fieldnames + ['matched_contact_id']
    writer = csv.DictWriter(f_out, fieldnames=fieldnames)
    writer.writeheader()
    for row in reader:
        # Try to match company name from discussion log to contacts
        company = (row.get('company') or '').strip().lower()
        matched_id = company_to_id.get(company, '')
        row['matched_contact_id'] = matched_id
        writer.writerow(row)

print(f"Done. Output written to {OUTPUT_FILE}")
