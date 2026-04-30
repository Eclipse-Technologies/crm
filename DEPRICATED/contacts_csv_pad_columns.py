import csv

input_path = r"contacts.csv"
output_path = r"contacts_fixed.csv"

with open(input_path, newline='', encoding='utf-8') as infile:
    reader = csv.reader(infile)
    header = next(reader)
    num_columns = len(header)

    with open(output_path, 'w', newline='', encoding='utf-8') as outfile:
        writer = csv.writer(outfile)
        writer.writerow(header)
        for row in reader:
            # Pad the row with empty strings if it's too short
            if len(row) < num_columns:
                row += [''] * (num_columns - len(row))
            # Truncate the row if it's too long
            if len(row) > num_columns:
                row = row[:num_columns]
            writer.writerow(row)

print("contacts_fixed.csv created with all rows padded/truncated to correct column count.")
