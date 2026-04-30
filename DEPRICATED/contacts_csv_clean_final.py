import csv

input_path = r"contacts_fixed.csv"  # Use the file you just generated
output_path = r"contacts_final.csv"

with open(input_path, newline='', encoding='utf-8') as infile:
    reader = csv.reader(infile)
    header = next(reader)
    # Remove duplicate header if present
    if header[0] != 'id':
        header = next(reader)
    num_columns = len(header)

    with open(output_path, 'w', newline='', encoding='utf-8') as outfile:
        writer = csv.writer(outfile)
        writer.writerow(header)
        for row in reader:
            # Skip duplicate header rows
            if row == header:
                continue
            # Pad or trim the row
            if len(row) < num_columns:
                row += [''] * (num_columns - len(row))
            if len(row) > num_columns:
                row = row[:num_columns]
            writer.writerow(row)

print("contacts_final.csv created with a single header and all rows padded/truncated to 18 columns.")
