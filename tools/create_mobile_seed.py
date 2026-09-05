"""Convert the Windows Dayong SQLite database into the Android seed database."""
import datetime as dt
import hashlib
import os
import sqlite3
import sys


def ticks(value):
    if not value:
        return None
    parsed = dt.datetime.fromisoformat(value)
    epoch = dt.datetime(1, 1, 1)
    return int((parsed - epoch).total_seconds() * 10_000_000)


source_path, destination_path = sys.argv[1], sys.argv[2]
os.makedirs(os.path.dirname(destination_path), exist_ok=True)
if os.path.exists(destination_path):
    os.remove(destination_path)

source = sqlite3.connect(source_path)
source.row_factory = sqlite3.Row
target = sqlite3.connect(destination_path)
target.executescript("""
CREATE TABLE MobileUser (Id INTEGER PRIMARY KEY AUTOINCREMENT, Username TEXT, PasswordHash TEXT);
CREATE TABLE MobileMember (Id INTEGER PRIMARY KEY AUTOINCREMENT, LastName TEXT, FirstName TEXT, Council TEXT,
 RegistrationDate INTEGER, StartCycleId INTEGER NULL, Status TEXT, DateOfDeath INTEGER NULL);
CREATE TABLE MobileCycle (Id INTEGER PRIMARY KEY AUTOINCREMENT, Name TEXT, Type TEXT, ExpectedAmount REAL,
 StartDate INTEGER NULL, DueDate INTEGER NULL, Active INTEGER);
CREATE TABLE MobilePayment (Id INTEGER PRIMARY KEY AUTOINCREMENT, MemberId INTEGER, CycleId INTEGER,
 Amount REAL, DatePaid INTEGER, ReceiptNumber TEXT);
CREATE INDEX MobilePayment_MemberId ON MobilePayment(MemberId);
CREATE INDEX MobilePayment_CycleId ON MobilePayment(CycleId);
""")
password_hash = hashlib.sha256(b"Dayong@2026").hexdigest().upper()
target.execute("INSERT INTO MobileUser(Username,PasswordHash) VALUES(?,?)", ("admin", password_hash))

for row in source.execute("SELECT Id,LastName,FirstName,Council,RegistrationDate,StartCycleId,MemberStatus,DateOfDeath FROM Members"):
    registration = ticks(row["RegistrationDate"]) or ticks(dt.date.today().isoformat())
    target.execute("INSERT INTO MobileMember VALUES(?,?,?,?,?,?,?,?)", (
        row["Id"], row["LastName"], row["FirstName"], row["Council"], registration,
        row["StartCycleId"], row["MemberStatus"] or "Active", ticks(row["DateOfDeath"])))

for row in source.execute("SELECT Id,Name,Type,ExpectedAmount,StartDate,DueDate,Active FROM CollectionCycles"):
    target.execute("INSERT INTO MobileCycle VALUES(?,?,?,?,?,?,?)", (
        row["Id"], row["Name"], row["Type"], row["ExpectedAmount"], ticks(row["StartDate"]),
        ticks(row["DueDate"]), row["Active"]))

payment_columns = {row[1] for row in source.execute("PRAGMA table_info(Payments)")}
receipt_expression = "ReceiptNumber" if "ReceiptNumber" in payment_columns else "'' AS ReceiptNumber"
for row in source.execute(f"SELECT Id,MemberId,CycleId,Amount,DatePaid,{receipt_expression} FROM Payments"):
    target.execute("INSERT INTO MobilePayment VALUES(?,?,?,?,?,?)", (
        row["Id"], row["MemberId"], row["CycleId"], row["Amount"],
        ticks(row["DatePaid"]) or ticks(dt.date.today().isoformat()), row["ReceiptNumber"] or ""))

target.commit()
print(f"Seeded {target.execute('SELECT COUNT(*) FROM MobileMember').fetchone()[0]} members, "
      f"{target.execute('SELECT COUNT(*) FROM MobileCycle').fetchone()[0]} cycles, and "
      f"{target.execute('SELECT COUNT(*) FROM MobilePayment').fetchone()[0]} payments.")
target.close()
source.close()
