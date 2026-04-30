import csv

# Path to the contacts.csv file
input_path = r"contacts.csv"
output_path = r"contacts_cleaned.csv"
skipped_log_path = r"contacts_cleaned_skipped.log"

# Read the header to determine the correct number of columns
with open(input_path, newline='', encoding='utf-8') as infile:
    reader = csv.reader(infile)
    header = next(reader)
    # If the file has a duplicate header row, skip it
    first_row = next(reader)
    if first_row == header:
        print("Duplicate header row found, skipping second header.")
    else:
        infile.seek(0)
        next(reader)  # reset to just after header
        first_row = None
    num_columns = len(header)

with open(input_path, newline='', encoding='utf-8') as infile, \
     open(output_path, 'w', newline='', encoding='utf-8') as outfile, \
     open(skipped_log_path, 'w', encoding='utf-8') as skippedfile:
    reader = csv.reader(infile)
    writer = csv.writer(outfile)
    header = next(reader)
    writer.writerow(header)
    for row in reader:
        if row == header:
            continue  # skip duplicate header rows
        if len(row) == num_columns:
            writer.writerow(row)
        else:
            skippedfile.write(','.join(row) + '\n')

print("Cleaning complete. Rows with mismatched columns have been logged and removed.")
