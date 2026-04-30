import os

# Directory containing your PHP files
source_dir = r"C:\\xampp\htdocs"
output_file = "combined_php.txt"

with open(output_file, "w", encoding="utf-8") as outfile:
    for root, dirs, files in os.walk(source_dir):
        for file in files:
            if file.endswith(".php"):
                file_path = os.path.join(root, file)
                outfile.write(f"\n\n--- Start of {file} ---\n\n")
                with open(file_path, "r", encoding="utf-8", errors="ignore") as infile:
                    outfile.write(infile.read())
                outfile.write(f"\n\n--- End of {file} ---\n\n")

print(f"All PHP files have been combined into {output_file}")