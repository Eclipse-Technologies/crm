# contacts_csv_cleaner_v2.py
"""
This script reads contacts.csv, checks each row for the correct number of columns (as defined by the header), and writes only valid rows to a new file. Malformed rows are skipped and logged.
"""
import csv

INPUT = 'contacts.csv'
OUTPUT = 'contacts_cleaned.csv'
LOG = 'contacts_cleaned_skipped.log'

def main():
    with open(INPUT, newline='', encoding='utf-8') as infile, \
         open(OUTPUT, 'w', newline='', encoding='utf-8') as outfile, \
         open(LOG, 'w', encoding='utf-8') as log:
        reader = csv.reader(infile)
        writer = csv.writer(outfile)
        header = next(reader)
        col_count = len(header)
        writer.writerow(header)
        for i, row in enumerate(reader, start=2):
            if len(row) == col_count:
                writer.writerow(row)
            else:
                log.write(f"Line {i}: {row} (columns: {len(row)})\n")

if __name__ == '__main__':
    main()
