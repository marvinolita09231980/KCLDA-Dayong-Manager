using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using Microsoft.Data.Sqlite;

namespace DayongManager;

public sealed class DatabaseService
{
	private readonly string _folder = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "KCLDA", "DayongManager");

	public string DatabasePath => Path.Combine(_folder, "dayong.db");

	private string ConnectionString => "Data Source=" + DatabasePath;

	public void Initialize()
	{
		Directory.CreateDirectory(_folder);
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "PRAGMA foreign_keys=ON;\nCREATE TABLE IF NOT EXISTS Members(\n  Id INTEGER PRIMARY KEY AUTOINCREMENT, LastName TEXT NOT NULL, FirstName TEXT NOT NULL,\n  MiddleName TEXT NOT NULL DEFAULT '', Address TEXT NOT NULL DEFAULT '', BirthDate TEXT NULL,\n  Council TEXT NOT NULL, Active INTEGER NOT NULL DEFAULT 1,\n  UNIQUE(LastName, FirstName, MiddleName, Council));\nCREATE TABLE IF NOT EXISTS CollectionCycles(\n  Id INTEGER PRIMARY KEY AUTOINCREMENT, Name TEXT NOT NULL UNIQUE,\n  Type TEXT NOT NULL CHECK(Type IN ('Dayong','Annual Dues','Registration Fee')),\n  ExpectedAmount REAL NOT NULL, DueDate TEXT NULL, Active INTEGER NOT NULL DEFAULT 1);\nCREATE TABLE IF NOT EXISTS Payments(\n  Id INTEGER PRIMARY KEY AUTOINCREMENT, MemberId INTEGER NOT NULL, CycleId INTEGER NOT NULL,\n  Amount REAL NOT NULL DEFAULT 0, DatePaid TEXT NULL, Notes TEXT NOT NULL DEFAULT '',\n  UNIQUE(MemberId, CycleId), FOREIGN KEY(MemberId) REFERENCES Members(Id) ON DELETE CASCADE,\n  FOREIGN KEY(CycleId) REFERENCES CollectionCycles(Id) ON DELETE CASCADE);";
		sqliteCommand.ExecuteNonQuery();
		EnsureCycleTypes(sqliteConnection);
		AddColumn(sqliteConnection, "Members", "MembershipType", "TEXT NOT NULL DEFAULT 'Brother Knight'");
		AddColumn(sqliteConnection, "Members", "SponsorName", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "ContactNumber", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "BeneficiaryName", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "BeneficiaryContact", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "IsFourthDegree", "INTEGER NOT NULL DEFAULT 0");
		AddColumn(sqliteConnection, "Members", "MemberStatus", "TEXT NOT NULL DEFAULT 'Active'");
		AddColumn(sqliteConnection, "Members", "Remarks", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "RegistrationDate", "TEXT NULL");
		AddColumn(sqliteConnection, "Members", "StartCycleId", "INTEGER NULL");
		AddColumn(sqliteConnection, "Members", "ClaimedBenefits", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "ServiceDate", "TEXT NULL");
		AddColumn(sqliteConnection, "Members", "ClaimReceivedDate", "TEXT NULL");
		AddColumn(sqliteConnection, "Members", "ClaimReceivedBy", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "Members", "DateOfDeath", "TEXT NULL");
		AddColumn(sqliteConnection, "CollectionCycles", "StartDate", "TEXT NULL");
		AddColumn(sqliteConnection, "Payments", "ReceiptNumber", "TEXT NOT NULL DEFAULT ''");
		using SqliteCommand sqliteCommand2 = sqliteConnection.CreateCommand();
		sqliteCommand2.CommandText = "CREATE TABLE IF NOT EXISTS AppUsers(Id INTEGER PRIMARY KEY AUTOINCREMENT,Username TEXT NOT NULL UNIQUE COLLATE NOCASE,PasswordHash TEXT NOT NULL,PasswordSalt TEXT NOT NULL,Active INTEGER NOT NULL DEFAULT 1)";
		sqliteCommand2.ExecuteNonQuery();
		AddColumn(sqliteConnection, "AppUsers", "DisplayName", "TEXT NOT NULL DEFAULT ''");
		AddColumn(sqliteConnection, "AppUsers", "IsAdmin", "INTEGER NOT NULL DEFAULT 0");
		AddColumn(sqliteConnection, "AppUsers", "Permissions", "TEXT NOT NULL DEFAULT ''");
		EnsureDefaultUser(sqliteConnection);
		using SqliteCommand adminMigration = sqliteConnection.CreateCommand();
		adminMigration.CommandText = "UPDATE AppUsers SET IsAdmin=1,DisplayName=CASE WHEN DisplayName='' THEN 'System Administrator' ELSE DisplayName END WHERE Username='admin' COLLATE NOCASE";
		adminMigration.ExecuteNonQuery();
		using SqliteCommand ledger = sqliteConnection.CreateCommand();
		ledger.CommandText = "CREATE TABLE IF NOT EXISTS BankTransactions(Id INTEGER PRIMARY KEY AUTOINCREMENT,TransactionDate TEXT NOT NULL,TransactionType TEXT NOT NULL CHECK(TransactionType IN ('Deposit','Withdrawal')),Amount REAL NOT NULL,ReferenceNumber TEXT NOT NULL DEFAULT '',Description TEXT NOT NULL DEFAULT '',RecordedBy TEXT NOT NULL DEFAULT '',CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)";
		ledger.ExecuteNonQuery();
		using SqliteCommand disbursements = sqliteConnection.CreateCommand();
		disbursements.CommandText = "CREATE TABLE IF NOT EXISTS Disbursements(Id INTEGER PRIMARY KEY AUTOINCREMENT,DisbursementDate TEXT NOT NULL,VoucherNumber TEXT NOT NULL DEFAULT '',Payee TEXT NOT NULL DEFAULT '',Category TEXT NOT NULL DEFAULT 'Other Expense',Particulars TEXT NOT NULL DEFAULT '',Amount REAL NOT NULL,RecordedBy TEXT NOT NULL DEFAULT '',CreatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)";
		disbursements.ExecuteNonQuery();
		NormalizeLegacyRegistrationCycle(sqliteConnection, "CY 2025 Annual Dues", "CY 2025 Registration Fee");
		NormalizeLegacyRegistrationCycle(sqliteConnection, "CY 2026 Annual Dues", "CY 2026 Registration Fee");
		using SqliteCommand optionalAnnual = sqliteConnection.CreateCommand();
		optionalAnnual.CommandText = "INSERT OR IGNORE INTO CollectionCycles(Name,Type,ExpectedAmount,DueDate,Active) VALUES('CY 2026 Annual Dues (Optional)','Annual Dues',100,'2026-12-31',1)";
		optionalAnnual.ExecuteNonQuery();
		using SqliteCommand sqliteCommand3 = sqliteConnection.CreateCommand();
		sqliteCommand3.CommandText = "UPDATE Members SET StartCycleId=(SELECT MIN(P.CycleId) FROM Payments P INNER JOIN CollectionCycles C ON C.Id=P.CycleId WHERE P.MemberId=Members.Id AND C.Type='Dayong') WHERE StartCycleId IS NULL AND EXISTS(SELECT 1 FROM Payments P INNER JOIN CollectionCycles C ON C.Id=P.CycleId WHERE P.MemberId=Members.Id AND C.Type='Dayong')";
		sqliteCommand3.ExecuteNonQuery();
	}

	private static void EnsureDefaultUser(SqliteConnection cn)
	{
		using SqliteCommand sqliteCommand = cn.CreateCommand();
		sqliteCommand.CommandText = "SELECT COUNT(*) FROM AppUsers";
		if (Convert.ToInt32(sqliteCommand.ExecuteScalar()) > 0)
		{
			return;
		}
		byte[] bytes = RandomNumberGenerator.GetBytes(16);
		byte[] inArray = HashPassword("Dayong@2026", bytes);
		using SqliteCommand sqliteCommand2 = cn.CreateCommand();
		sqliteCommand2.CommandText = "INSERT INTO AppUsers(Username,PasswordHash,PasswordSalt,Active) VALUES('admin',$h,$s,1)";
		sqliteCommand2.Parameters.AddWithValue("$h", Convert.ToBase64String(inArray));
		sqliteCommand2.Parameters.AddWithValue("$s", Convert.ToBase64String(bytes));
		sqliteCommand2.ExecuteNonQuery();
	}

	private static byte[] HashPassword(string password, byte[] salt)
	{
		return Rfc2898DeriveBytes.Pbkdf2(Encoding.UTF8.GetBytes(password), salt, 100000, HashAlgorithmName.SHA256, 32);
	}

	public bool Authenticate(string username, string password)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "SELECT PasswordHash,PasswordSalt FROM AppUsers WHERE Username=$u AND Active=1";
		sqliteCommand.Parameters.AddWithValue("$u", username.Trim());
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		if (!sqliteDataReader.Read())
		{
			return false;
		}
		byte[] array = Convert.FromBase64String(sqliteDataReader.GetString(0));
		return CryptographicOperations.FixedTimeEquals(right: HashPassword(password, Convert.FromBase64String(sqliteDataReader.GetString(1))), left: array);
	}

	public bool ChangePassword(string username, string currentPassword, string newPassword)
	{
		if (!Authenticate(username, currentPassword))
		{
			return false;
		}
		byte[] bytes = RandomNumberGenerator.GetBytes(16);
		byte[] inArray = HashPassword(newPassword, bytes);
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "UPDATE AppUsers SET PasswordHash=$h,PasswordSalt=$s WHERE Username=$u";
		sqliteCommand.Parameters.AddWithValue("$h", Convert.ToBase64String(inArray));
		sqliteCommand.Parameters.AddWithValue("$s", Convert.ToBase64String(bytes));
		sqliteCommand.Parameters.AddWithValue("$u", username.Trim());
		return sqliteCommand.ExecuteNonQuery() == 1;
	}

	public UserAccess? GetUserAccess(string username)
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "SELECT Id,Username,DisplayName,Active,IsAdmin,Permissions FROM AppUsers WHERE Username=$u";
		cmd.Parameters.AddWithValue("$u", username.Trim());
		using SqliteDataReader reader = cmd.ExecuteReader();
		return reader.Read() ? ReadUser(reader) : null;
	}

	public List<UserAccess> GetUsers()
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "SELECT Id,Username,DisplayName,Active,IsAdmin,Permissions FROM AppUsers ORDER BY Username";
		using SqliteDataReader reader = cmd.ExecuteReader();
		List<UserAccess> users = new List<UserAccess>();
		while (reader.Read()) users.Add(ReadUser(reader));
		return users;
	}

	private static UserAccess ReadUser(SqliteDataReader reader) => new UserAccess
	{
		Id = reader.GetInt64(0), Username = reader.GetString(1), DisplayName = reader.GetString(2),
		Active = reader.GetBoolean(3), IsAdmin = reader.GetBoolean(4), PermissionsText = reader.GetString(5)
	};

	public long SaveUser(UserAccess user, string newPassword)
	{
		using SqliteConnection cn = Open();
		if (user.Id == 0)
		{
			if (string.IsNullOrWhiteSpace(newPassword)) throw new InvalidOperationException("A password is required for a new user.");
			byte[] salt = RandomNumberGenerator.GetBytes(16);
			using SqliteCommand insert = cn.CreateCommand();
			insert.CommandText = "INSERT INTO AppUsers(Username,DisplayName,PasswordHash,PasswordSalt,Active,IsAdmin,Permissions) VALUES($u,$d,$h,$s,$a,$i,$p); SELECT last_insert_rowid();";
			insert.Parameters.AddWithValue("$u", user.Username.Trim()); insert.Parameters.AddWithValue("$d", user.DisplayName.Trim());
			insert.Parameters.AddWithValue("$h", Convert.ToBase64String(HashPassword(newPassword, salt))); insert.Parameters.AddWithValue("$s", Convert.ToBase64String(salt));
			insert.Parameters.AddWithValue("$a", user.Active); insert.Parameters.AddWithValue("$i", user.IsAdmin); insert.Parameters.AddWithValue("$p", user.PermissionsText);
			return Convert.ToInt64(insert.ExecuteScalar());
		}
		using SqliteCommand update = cn.CreateCommand();
		update.CommandText = "UPDATE AppUsers SET Username=$u,DisplayName=$d,Active=$a,IsAdmin=$i,Permissions=$p WHERE Id=$id";
		update.Parameters.AddWithValue("$u", user.Username.Trim()); update.Parameters.AddWithValue("$d", user.DisplayName.Trim());
		update.Parameters.AddWithValue("$a", user.Active); update.Parameters.AddWithValue("$i", user.IsAdmin); update.Parameters.AddWithValue("$p", user.PermissionsText); update.Parameters.AddWithValue("$id", user.Id);
		update.ExecuteNonQuery();
		if (!string.IsNullOrWhiteSpace(newPassword))
		{
			byte[] salt = RandomNumberGenerator.GetBytes(16);
			using SqliteCommand password = cn.CreateCommand();
			password.CommandText = "UPDATE AppUsers SET PasswordHash=$h,PasswordSalt=$s WHERE Id=$id";
			password.Parameters.AddWithValue("$h", Convert.ToBase64String(HashPassword(newPassword, salt))); password.Parameters.AddWithValue("$s", Convert.ToBase64String(salt)); password.Parameters.AddWithValue("$id", user.Id);
			password.ExecuteNonQuery();
		}
		return user.Id;
	}

	public void DeleteUser(long id)
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "DELETE FROM AppUsers WHERE Id=$id AND Username<>'admin' COLLATE NOCASE";
		cmd.Parameters.AddWithValue("$id", id); cmd.ExecuteNonQuery();
	}

	public List<BankTransaction> GetBankTransactions()
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "SELECT Id,TransactionDate,TransactionType,Amount,ReferenceNumber,Description,RecordedBy FROM BankTransactions ORDER BY TransactionDate,Id";
		using SqliteDataReader reader = cmd.ExecuteReader();
		List<BankTransaction> rows = new List<BankTransaction>();
		while (reader.Read()) rows.Add(new BankTransaction
		{
			Id = reader.GetInt64(0), TransactionDate = DateTime.Parse(reader.GetString(1)), TransactionType = reader.GetString(2),
			Amount = reader.GetDecimal(3), ReferenceNumber = reader.GetString(4), Description = reader.GetString(5), RecordedBy = reader.GetString(6)
		});
		return rows;
	}

	public long SaveBankTransaction(BankTransaction transaction, string recordedBy)
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = transaction.Id == 0
			? "INSERT INTO BankTransactions(TransactionDate,TransactionType,Amount,ReferenceNumber,Description,RecordedBy) VALUES($d,$t,$a,$r,$x,$u); SELECT last_insert_rowid();"
			: "UPDATE BankTransactions SET TransactionDate=$d,TransactionType=$t,Amount=$a,ReferenceNumber=$r,Description=$x WHERE Id=$id; SELECT $id;";
		cmd.Parameters.AddWithValue("$d", transaction.TransactionDate.ToString("yyyy-MM-dd")); cmd.Parameters.AddWithValue("$t", transaction.TransactionType);
		cmd.Parameters.AddWithValue("$a", transaction.Amount); cmd.Parameters.AddWithValue("$r", transaction.ReferenceNumber.Trim()); cmd.Parameters.AddWithValue("$x", transaction.Description.Trim());
		cmd.Parameters.AddWithValue("$u", recordedBy); cmd.Parameters.AddWithValue("$id", transaction.Id);
		return Convert.ToInt64(cmd.ExecuteScalar());
	}

	public void DeleteBankTransaction(long id)
	{
		using SqliteConnection cn = Open(); using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "DELETE FROM BankTransactions WHERE Id=$id"; cmd.Parameters.AddWithValue("$id", id); cmd.ExecuteNonQuery();
	}

	public List<Disbursement> GetDisbursements()
	{
		using SqliteConnection cn = Open(); using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "SELECT Id,DisbursementDate,VoucherNumber,Payee,Category,Particulars,Amount,RecordedBy FROM Disbursements ORDER BY DisbursementDate,Id";
		using SqliteDataReader reader = cmd.ExecuteReader(); List<Disbursement> rows = new List<Disbursement>();
		while (reader.Read()) rows.Add(new Disbursement { Id = reader.GetInt64(0), DisbursementDate = DateTime.Parse(reader.GetString(1)),
			VoucherNumber = reader.GetString(2), Payee = reader.GetString(3), Category = reader.GetString(4), Particulars = reader.GetString(5), Amount = reader.GetDecimal(6), RecordedBy = reader.GetString(7) });
		return rows;
	}

	public long SaveDisbursement(Disbursement expense, string recordedBy)
	{
		using SqliteConnection cn = Open(); using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = expense.Id == 0
			? "INSERT INTO Disbursements(DisbursementDate,VoucherNumber,Payee,Category,Particulars,Amount,RecordedBy) VALUES($d,$v,$p,$c,$x,$a,$u); SELECT last_insert_rowid();"
			: "UPDATE Disbursements SET DisbursementDate=$d,VoucherNumber=$v,Payee=$p,Category=$c,Particulars=$x,Amount=$a WHERE Id=$id; SELECT $id;";
		cmd.Parameters.AddWithValue("$d", expense.DisbursementDate.ToString("yyyy-MM-dd")); cmd.Parameters.AddWithValue("$v", expense.VoucherNumber.Trim());
		cmd.Parameters.AddWithValue("$p", expense.Payee.Trim()); cmd.Parameters.AddWithValue("$c", expense.Category.Trim()); cmd.Parameters.AddWithValue("$x", expense.Particulars.Trim());
		cmd.Parameters.AddWithValue("$a", expense.Amount); cmd.Parameters.AddWithValue("$u", recordedBy); cmd.Parameters.AddWithValue("$id", expense.Id);
		return Convert.ToInt64(cmd.ExecuteScalar());
	}

	public void DeleteDisbursement(long id)
	{
		using SqliteConnection cn = Open(); using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = "DELETE FROM Disbursements WHERE Id=$id"; cmd.Parameters.AddWithValue("$id", id); cmd.ExecuteNonQuery();
	}

	private static void EnsureCycleTypes(SqliteConnection cn)
	{
		using SqliteCommand sqliteCommand = cn.CreateCommand();
		sqliteCommand.CommandText = "SELECT sql FROM sqlite_master WHERE type='table' AND name='CollectionCycles'";
		if ((Convert.ToString(sqliteCommand.ExecuteScalar()) ?? "").Contains("Registration Fee"))
		{
			return;
		}
		using SqliteCommand sqliteCommand2 = cn.CreateCommand();
		sqliteCommand2.CommandText = "PRAGMA foreign_keys=OFF;\nCREATE TABLE CollectionCycles_New(Id INTEGER PRIMARY KEY AUTOINCREMENT,Name TEXT NOT NULL UNIQUE,Type TEXT NOT NULL CHECK(Type IN ('Dayong','Annual Dues','Registration Fee')),ExpectedAmount REAL NOT NULL,DueDate TEXT NULL,Active INTEGER NOT NULL DEFAULT 1);\nINSERT INTO CollectionCycles_New SELECT * FROM CollectionCycles;\nDROP TABLE CollectionCycles;\nALTER TABLE CollectionCycles_New RENAME TO CollectionCycles;\nPRAGMA foreign_keys=ON;";
		sqliteCommand2.ExecuteNonQuery();
	}

	private static void NormalizeLegacyRegistrationCycle(SqliteConnection cn, string oldName, string newName)
	{
		using SqliteCommand find = cn.CreateCommand();
		find.CommandText = "SELECT Id,Name FROM CollectionCycles WHERE Name=$old OR Name=$new ORDER BY CASE WHEN Name=$new THEN 0 ELSE 1 END";
		find.Parameters.AddWithValue("$old", oldName); find.Parameters.AddWithValue("$new", newName);
		long? oldId = null; long? newId = null;
		using (SqliteDataReader reader = find.ExecuteReader())
		{
			while (reader.Read())
			{
				long id = reader.GetInt64(0); string name = reader.GetString(1);
				if (name == oldName) oldId = id; else newId = id;
			}
		}
		if (!oldId.HasValue) return;
		if (!newId.HasValue)
		{
			using SqliteCommand rename = cn.CreateCommand();
			rename.CommandText = "UPDATE CollectionCycles SET Name=$new,Type='Registration Fee',ExpectedAmount=100 WHERE Id=$id";
			rename.Parameters.AddWithValue("$new", newName); rename.Parameters.AddWithValue("$id", oldId.Value); rename.ExecuteNonQuery();
			return;
		}
		using SqliteCommand merge = cn.CreateCommand();
		merge.CommandText = @"INSERT INTO Payments(MemberId,CycleId,Amount,DatePaid,Notes)
			SELECT MemberId,$new,Amount,DatePaid,Notes FROM Payments WHERE CycleId=$old
			ON CONFLICT(MemberId,CycleId) DO UPDATE SET Amount=MAX(Payments.Amount,excluded.Amount),DatePaid=COALESCE(Payments.DatePaid,excluded.DatePaid);
			DELETE FROM CollectionCycles WHERE Id=$old;";
		merge.Parameters.AddWithValue("$new", newId.Value); merge.Parameters.AddWithValue("$old", oldId.Value); merge.ExecuteNonQuery();
	}

	private static void AddColumn(SqliteConnection cn, string table, string column, string definition)
	{
		using SqliteCommand sqliteCommand = cn.CreateCommand();
		sqliteCommand.CommandText = "PRAGMA table_info(" + table + ")";
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		while (sqliteDataReader.Read())
		{
			if (sqliteDataReader.GetString(1).Equals(column, StringComparison.OrdinalIgnoreCase))
			{
				return;
			}
		}
		sqliteDataReader.Close();
		using SqliteCommand sqliteCommand2 = cn.CreateCommand();
		sqliteCommand2.CommandText = $"ALTER TABLE {table} ADD COLUMN {column} {definition}";
		sqliteCommand2.ExecuteNonQuery();
	}

	private SqliteConnection Open()
	{
		SqliteConnection sqliteConnection = new SqliteConnection(ConnectionString);
		sqliteConnection.Open();
		return sqliteConnection;
	}

	public int MemberCount()
	{
		return ScalarInt("SELECT COUNT(*) FROM Members");
	}

	private int ScalarInt(string sql)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = sql;
		return Convert.ToInt32(sqliteCommand.ExecuteScalar());
	}

	public List<Member> GetMembers(string search = "", string council = "All Councils")
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "SELECT Id,LastName,FirstName,MiddleName,Address,BirthDate,Council,Active,MembershipType,SponsorName,ContactNumber,BeneficiaryName,BeneficiaryContact,IsFourthDegree,MemberStatus,Remarks,RegistrationDate,StartCycleId,ClaimedBenefits,ServiceDate,ClaimReceivedDate,ClaimReceivedBy,DateOfDeath FROM Members WHERE ($c='All Councils' OR Council=$c) AND ($q='' OR LastName LIKE $like OR FirstName LIKE $like OR MiddleName LIKE $like) ORDER BY Council,LastName,FirstName";
		sqliteCommand.Parameters.AddWithValue("$c", council);
		sqliteCommand.Parameters.AddWithValue("$q", search);
		sqliteCommand.Parameters.AddWithValue("$like", "%" + search + "%");
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		List<Member> list = new List<Member>();
		while (sqliteDataReader.Read())
		{
			list.Add(new Member
			{
				Id = sqliteDataReader.GetInt64(0),
				LastName = sqliteDataReader.GetString(1),
				FirstName = sqliteDataReader.GetString(2),
				MiddleName = sqliteDataReader.GetString(3),
				Address = sqliteDataReader.GetString(4),
				BirthDate = (sqliteDataReader.IsDBNull(5) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(5)))),
				Council = sqliteDataReader.GetString(6),
				Active = sqliteDataReader.GetBoolean(7),
				MembershipType = sqliteDataReader.GetString(8),
				SponsorName = sqliteDataReader.GetString(9),
				ContactNumber = sqliteDataReader.GetString(10),
				BeneficiaryName = sqliteDataReader.GetString(11),
				BeneficiaryContact = sqliteDataReader.GetString(12),
				IsFourthDegree = sqliteDataReader.GetBoolean(13),
				MemberStatus = sqliteDataReader.GetString(14),
				Remarks = sqliteDataReader.GetString(15),
				RegistrationDate = (sqliteDataReader.IsDBNull(16) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(16)))),
				StartCycleId = (sqliteDataReader.IsDBNull(17) ? ((long?)null) : new long?(sqliteDataReader.GetInt64(17))),
				ClaimedBenefits = sqliteDataReader.GetString(18),
				ServiceDate = (sqliteDataReader.IsDBNull(19) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(19)))),
				ClaimReceivedDate = (sqliteDataReader.IsDBNull(20) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(20)))),
				ClaimReceivedBy = sqliteDataReader.GetString(21),
				DateOfDeath = (sqliteDataReader.IsDBNull(22) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(22))))
			});
		}
		return list;
	}

	public List<string> GetCouncils()
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "SELECT DISTINCT Council FROM Members WHERE Council<>'' ORDER BY Council";
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		List<string> list = new List<string>();
		while (sqliteDataReader.Read())
		{
			list.Add(sqliteDataReader.GetString(0));
		}
		return list;
	}

	public long SaveMember(Member m)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = ((m.Id == 0L) ? "INSERT INTO Members(LastName,FirstName,MiddleName,Address,BirthDate,Council,Active,MembershipType,SponsorName,ContactNumber,BeneficiaryName,BeneficiaryContact,IsFourthDegree,MemberStatus,Remarks,RegistrationDate,StartCycleId,ClaimedBenefits,ServiceDate,ClaimReceivedDate,ClaimReceivedBy,DateOfDeath) VALUES($l,$f,$m,$a,$b,$c,$x,$mt,$sn,$cn,$bn,$bc,$fd,$st,$rm,$rd,$sc,$cb,$sd,$cd,$cr,$dd); SELECT last_insert_rowid();" : "UPDATE Members SET LastName=$l,FirstName=$f,MiddleName=$m,Address=$a,BirthDate=$b,Council=$c,Active=$x,MembershipType=$mt,SponsorName=$sn,ContactNumber=$cn,BeneficiaryName=$bn,BeneficiaryContact=$bc,IsFourthDegree=$fd,MemberStatus=$st,Remarks=$rm,RegistrationDate=$rd,StartCycleId=$sc,ClaimedBenefits=$cb,ServiceDate=$sd,ClaimReceivedDate=$cd,ClaimReceivedBy=$cr,DateOfDeath=$dd WHERE Id=$id; SELECT $id;");
		sqliteCommand.Parameters.AddWithValue("$l", m.LastName.Trim());
		sqliteCommand.Parameters.AddWithValue("$f", m.FirstName.Trim());
		sqliteCommand.Parameters.AddWithValue("$m", m.MiddleName.Trim());
		sqliteCommand.Parameters.AddWithValue("$a", m.Address.Trim());
		sqliteCommand.Parameters.AddWithValue("$b", ((object)m.BirthDate?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$c", m.Council.Trim());
		sqliteCommand.Parameters.AddWithValue("$x", m.Active);
		sqliteCommand.Parameters.AddWithValue("$id", m.Id);
		sqliteCommand.Parameters.AddWithValue("$mt", m.MembershipType);
		sqliteCommand.Parameters.AddWithValue("$sn", m.SponsorName.Trim());
		sqliteCommand.Parameters.AddWithValue("$cn", m.ContactNumber.Trim());
		sqliteCommand.Parameters.AddWithValue("$bn", m.BeneficiaryName.Trim());
		sqliteCommand.Parameters.AddWithValue("$bc", m.BeneficiaryContact.Trim());
		sqliteCommand.Parameters.AddWithValue("$fd", m.IsFourthDegree);
		sqliteCommand.Parameters.AddWithValue("$st", m.MemberStatus);
		sqliteCommand.Parameters.AddWithValue("$rm", m.Remarks.Trim());
		sqliteCommand.Parameters.AddWithValue("$rd", ((object)m.RegistrationDate?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$sc", ((object)m.StartCycleId) ?? DBNull.Value);
		sqliteCommand.Parameters.AddWithValue("$cb", m.ClaimedBenefits.Trim());
		sqliteCommand.Parameters.AddWithValue("$sd", ((object)m.ServiceDate?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$cd", ((object)m.ClaimReceivedDate?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$cr", m.ClaimReceivedBy.Trim());
		sqliteCommand.Parameters.AddWithValue("$dd", ((object)m.DateOfDeath?.ToString("yyyy-MM-dd")) ?? DBNull.Value);
		return Convert.ToInt64(sqliteCommand.ExecuteScalar());
	}

	public void DeleteMember(long id)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "DELETE FROM Members WHERE Id=$id";
		sqliteCommand.Parameters.AddWithValue("$id", id);
		sqliteCommand.ExecuteNonQuery();
	}

	public List<CollectionCycle> GetCycles()
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "SELECT Id,Name,Type,ExpectedAmount,DueDate,Active,StartDate FROM CollectionCycles ORDER BY Id DESC";
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		List<CollectionCycle> list = new List<CollectionCycle>();
		while (sqliteDataReader.Read())
		{
			list.Add(new CollectionCycle
			{
				Id = sqliteDataReader.GetInt64(0),
				Name = sqliteDataReader.GetString(1),
				Type = sqliteDataReader.GetString(2),
				ExpectedAmount = sqliteDataReader.GetDecimal(3),
				DueDate = (sqliteDataReader.IsDBNull(4) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(4)))),
				Active = sqliteDataReader.GetBoolean(5),
				StartDate = (sqliteDataReader.IsDBNull(6) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(6))))
			});
		}
		return list;
	}

	public long SaveCycle(CollectionCycle x)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = ((x.Id == 0L) ? "INSERT INTO CollectionCycles(Name,Type,ExpectedAmount,DueDate,Active,StartDate) VALUES($n,$t,$a,$d,$x,$s); SELECT last_insert_rowid();" : "UPDATE CollectionCycles SET Name=$n,Type=$t,ExpectedAmount=$a,DueDate=$d,Active=$x,StartDate=$s WHERE Id=$id; SELECT $id;");
		sqliteCommand.Parameters.AddWithValue("$n", x.Name.Trim());
		sqliteCommand.Parameters.AddWithValue("$t", x.Type);
		sqliteCommand.Parameters.AddWithValue("$a", x.ExpectedAmount);
		sqliteCommand.Parameters.AddWithValue("$d", ((object)x.DueDate?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$s", ((object)x.StartDate?.ToString("yyyy-MM-dd")) ?? DBNull.Value);
		sqliteCommand.Parameters.AddWithValue("$x", x.Active);
		sqliteCommand.Parameters.AddWithValue("$id", x.Id);
		return Convert.ToInt64(sqliteCommand.ExecuteScalar());
	}

	public void DeleteCycle(long id)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = "DELETE FROM CollectionCycles WHERE Id=$id";
		sqliteCommand.Parameters.AddWithValue("$id", id);
		sqliteCommand.ExecuteNonQuery();
	}

	public List<PaymentRow> GetPaymentRows(long cycleId, string council = "All Councils")
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = @"SELECT COALESCE(P.Id,0),M.Id,
			M.LastName||', '||M.FirstName||CASE WHEN M.MiddleName='' THEN '' ELSE ' '||M.MiddleName END,
			M.Council,C.ExpectedAmount,COALESCE(P.Amount,0),P.DatePaid,COALESCE(P.ReceiptNumber,'')
			FROM Members M CROSS JOIN CollectionCycles C
			LEFT JOIN Payments P ON P.MemberId=M.Id AND P.CycleId=C.Id
			WHERE C.Id=$id
			AND (
				M.Active=1
				OR (M.MemberStatus='Deceased' AND M.DateOfDeath IS NOT NULL
					AND COALESCE(C.StartDate,C.DueDate,P.DatePaid) IS NOT NULL
					AND date(COALESCE(C.StartDate,C.DueDate,P.DatePaid))<=date(M.DateOfDeath))
			)
			AND (C.Type<>'Dayong' OR M.StartCycleId IS NULL OR C.Id>=M.StartCycleId)
			AND (C.Type<>'Registration Fee' OR P.Id IS NOT NULL OR C.Id=COALESCE(
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee' AND M.RegistrationDate IS NOT NULL
					AND (instr(CR.Name,substr(M.RegistrationDate,1,4))>0 OR substr(COALESCE(CR.StartDate,CR.DueDate),1,4)=substr(M.RegistrationDate,1,4))),
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee')))
			AND ($c='All Councils' OR M.Council=$c)
			ORDER BY M.Council,M.LastName,M.FirstName";
		sqliteCommand.Parameters.AddWithValue("$id", cycleId);
		sqliteCommand.Parameters.AddWithValue("$c", council);
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		List<PaymentRow> list = new List<PaymentRow>();
		while (sqliteDataReader.Read())
		{
			list.Add(new PaymentRow
			{
				PaymentId = sqliteDataReader.GetInt64(0),
				MemberId = sqliteDataReader.GetInt64(1),
				MemberName = sqliteDataReader.GetString(2),
				Council = sqliteDataReader.GetString(3),
				Expected = sqliteDataReader.GetDecimal(4),
				Paid = sqliteDataReader.GetDecimal(5),
				DatePaid = (sqliteDataReader.IsDBNull(6) ? ((DateTime?)null) : new DateTime?(DateTime.Parse(sqliteDataReader.GetString(6)))),
				ReceiptNumber = sqliteDataReader.GetString(7)
			});
		}
		return list;
	}

	public List<PaymentDue> GetMemberDues(long memberId, long selectedCycleId)
	{
		using SqliteConnection cn = Open();
		using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = @"SELECT C.Id,C.Name,C.Type,C.ExpectedAmount,COALESCE(P.Amount,0),
			CASE WHEN C.Id=$selected THEN 1 ELSE 0 END
			FROM Members M CROSS JOIN CollectionCycles C
			LEFT JOIN Payments P ON P.MemberId=M.Id AND P.CycleId=C.Id
			WHERE M.Id=$member
			AND (C.Id=$selected OR COALESCE(P.Amount,0)<C.ExpectedAmount)
			AND (C.Id=$selected OR C.Active=1)
			AND (C.Type<>'Dayong' OR M.StartCycleId IS NULL OR C.Id>=M.StartCycleId)
			AND (C.Type<>'Registration Fee' OR P.Id IS NOT NULL OR C.Id=COALESCE(
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee' AND M.RegistrationDate IS NOT NULL
					AND (instr(CR.Name,substr(M.RegistrationDate,1,4))>0 OR substr(COALESCE(CR.StartDate,CR.DueDate),1,4)=substr(M.RegistrationDate,1,4))),
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee')))
			AND (M.DateOfDeath IS NULL OR COALESCE(C.StartDate,C.DueDate,P.DatePaid) IS NULL
				OR date(COALESCE(C.StartDate,C.DueDate,P.DatePaid))<=date(M.DateOfDeath))
			ORDER BY CASE C.Type WHEN 'Registration Fee' THEN 0 WHEN 'Annual Dues' THEN 1 ELSE 2 END,
				COALESCE(C.StartDate,C.DueDate,'9999-12-31'),C.Id";
		cmd.Parameters.AddWithValue("$member", memberId);
		cmd.Parameters.AddWithValue("$selected", selectedCycleId);
		using SqliteDataReader reader = cmd.ExecuteReader();
		List<PaymentDue> dues = new List<PaymentDue>();
		while (reader.Read())
		{
			dues.Add(new PaymentDue
			{
				CycleId = reader.GetInt64(0), CycleName = reader.GetString(1), Type = reader.GetString(2),
				Required = reader.GetDecimal(3), PreviouslyPaid = reader.GetDecimal(4), IsSelectedCycle = reader.GetInt32(5) == 1
			});
		}
		return dues;
	}

	public void SavePayment(long memberId, long cycleId, decimal amount, DateTime? date, string receiptNumber = "")
	{
		SavePayments(memberId, new List<(long CycleId, decimal NewTotal)> { (cycleId, amount) }, date, receiptNumber);
	}

	public void SavePayments(long memberId, IEnumerable<(long CycleId, decimal NewTotal)> allocations, DateTime? date, string receiptNumber)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteTransaction transaction = sqliteConnection.BeginTransaction();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.Transaction = transaction;
		sqliteCommand.CommandText = "INSERT INTO Payments(MemberId,CycleId,Amount,DatePaid,ReceiptNumber) VALUES($m,$c,$a,$d,$r) ON CONFLICT(MemberId,CycleId) DO UPDATE SET Amount=$a,DatePaid=$d,ReceiptNumber=$r; UPDATE Members SET StartCycleId=$c WHERE Id=$m AND StartCycleId IS NULL AND EXISTS(SELECT 1 FROM CollectionCycles WHERE Id=$c AND Type='Dayong')";
		sqliteCommand.Parameters.AddWithValue("$m", memberId);
		sqliteCommand.Parameters.AddWithValue("$d", ((object)date?.ToString("yyyy-MM-dd")) ?? ((object)DBNull.Value));
		sqliteCommand.Parameters.AddWithValue("$r", receiptNumber.Trim());
		SqliteParameter cycleParameter = sqliteCommand.Parameters.Add("$c", SqliteType.Integer);
		SqliteParameter amountParameter = sqliteCommand.Parameters.Add("$a", SqliteType.Real);
		foreach ((long cycleId, decimal newTotal) in allocations)
		{
			cycleParameter.Value = cycleId;
			amountParameter.Value = newTotal;
			sqliteCommand.ExecuteNonQuery();
		}
		transaction.Commit();
	}

	public bool DeletePayment(long memberId, long cycleId)
	{
		using SqliteConnection connection = Open();
		using SqliteCommand command = connection.CreateCommand();
		command.CommandText = "DELETE FROM Payments WHERE MemberId=$member AND CycleId=$cycle";
		command.Parameters.AddWithValue("$member", memberId);
		command.Parameters.AddWithValue("$cycle", cycleId);
		return command.ExecuteNonQuery() > 0;
	}

	public LanSyncSnapshot SynchronizeMobilePayments(IEnumerable<LanSyncPayment> mobilePayments)
	{
		using (SqliteConnection connection = Open())
		using (SqliteTransaction transaction = connection.BeginTransaction())
		using (SqliteCommand command = connection.CreateCommand())
		{
			command.Transaction = transaction;
			command.CommandText = @"INSERT INTO Payments(MemberId,CycleId,Amount,DatePaid,ReceiptNumber)
				SELECT $member,$cycle,$amount,$date,$receipt
				WHERE EXISTS(SELECT 1 FROM Members WHERE Id=$member) AND EXISTS(SELECT 1 FROM CollectionCycles WHERE Id=$cycle)
				ON CONFLICT(MemberId,CycleId) DO UPDATE SET
				Amount=MAX(Payments.Amount,excluded.Amount),
				DatePaid=CASE WHEN date(excluded.DatePaid)>=date(Payments.DatePaid) THEN excluded.DatePaid ELSE Payments.DatePaid END,
				ReceiptNumber=CASE WHEN date(excluded.DatePaid)>=date(Payments.DatePaid) AND excluded.ReceiptNumber<>'' THEN excluded.ReceiptNumber ELSE Payments.ReceiptNumber END";
			foreach (LanSyncPayment payment in mobilePayments)
			{
				command.Parameters.Clear(); command.Parameters.AddWithValue("$member", payment.MemberId); command.Parameters.AddWithValue("$cycle", payment.CycleId);
				command.Parameters.AddWithValue("$amount", payment.Amount); command.Parameters.AddWithValue("$date", payment.DatePaid.ToString("yyyy-MM-dd")); command.Parameters.AddWithValue("$receipt", payment.ReceiptNumber ?? ""); command.ExecuteNonQuery();
			}
			transaction.Commit();
		}
		return GetLanSyncSnapshot();
	}

	private LanSyncSnapshot GetLanSyncSnapshot()
	{
		LanSyncSnapshot snapshot = new LanSyncSnapshot();
		snapshot.Members = GetMembers().Select(m => new LanSyncMember { Id=m.Id,LastName=m.LastName,FirstName=m.FirstName,Council=m.Council,RegistrationDate=m.RegistrationDate ?? DateTime.Today,StartCycleId=m.StartCycleId,Status=m.MemberStatus,DateOfDeath=m.DateOfDeath }).ToList();
		snapshot.Cycles = GetCycles().Select(c => new LanSyncCycle { Id=c.Id,Name=c.Name,Type=c.Type,ExpectedAmount=c.ExpectedAmount,StartDate=c.StartDate,DueDate=c.DueDate,Active=c.Active }).ToList();
		using SqliteConnection connection = Open(); using SqliteCommand command = connection.CreateCommand();
		command.CommandText = "SELECT Id,MemberId,CycleId,Amount,DatePaid,COALESCE(ReceiptNumber,'') FROM Payments";
		using SqliteDataReader reader = command.ExecuteReader();
		while (reader.Read()) snapshot.Payments.Add(new LanSyncPayment { Id=reader.GetInt64(0),MemberId=reader.GetInt64(1),CycleId=reader.GetInt64(2),Amount=reader.GetDecimal(3),DatePaid=reader.IsDBNull(4)?DateTime.Today:DateTime.Parse(reader.GetString(4)),ReceiptNumber=reader.GetString(5) });
		return snapshot;
	}

	public (int members, int paid, decimal collected, decimal expected) Dashboard(long? cycleId = null)
	{
		using SqliteConnection sqliteConnection = Open();
		using SqliteCommand sqliteCommand = sqliteConnection.CreateCommand();
		sqliteCommand.CommandText = (cycleId.HasValue ? @"SELECT COUNT(M.Id),
			SUM(CASE WHEN COALESCE(P.Amount,0)>=C.ExpectedAmount THEN 1 ELSE 0 END),
			COALESCE(SUM(P.Amount),0),COUNT(M.Id)*C.ExpectedAmount
			FROM Members M CROSS JOIN CollectionCycles C
			LEFT JOIN Payments P ON P.MemberId=M.Id AND P.CycleId=C.Id
			WHERE C.Id=$id
			AND (
				M.Active=1
				OR (M.MemberStatus='Deceased' AND M.DateOfDeath IS NOT NULL
					AND COALESCE(C.StartDate,C.DueDate,P.DatePaid) IS NOT NULL
					AND date(COALESCE(C.StartDate,C.DueDate,P.DatePaid))<=date(M.DateOfDeath))
			)
			AND (C.Type<>'Dayong' OR M.StartCycleId IS NULL OR C.Id>=M.StartCycleId)
			AND (C.Type<>'Registration Fee' OR P.Id IS NOT NULL OR C.Id=COALESCE(
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee' AND M.RegistrationDate IS NOT NULL
					AND (instr(CR.Name,substr(M.RegistrationDate,1,4))>0 OR substr(COALESCE(CR.StartDate,CR.DueDate),1,4)=substr(M.RegistrationDate,1,4))),
				(SELECT MIN(CR.Id) FROM CollectionCycles CR WHERE CR.Type='Registration Fee')))" : "SELECT COUNT(*),0,0,0 FROM Members WHERE Active=1");
		sqliteCommand.Parameters.AddWithValue("$id", cycleId.GetValueOrDefault());
		using SqliteDataReader sqliteDataReader = sqliteCommand.ExecuteReader();
		sqliteDataReader.Read();
		return (members: sqliteDataReader.GetInt32(0), paid: sqliteDataReader.GetInt32(1), collected: sqliteDataReader.GetDecimal(2), expected: sqliteDataReader.GetDecimal(3));
	}

	public (decimal totalCollections, decimal totalExpenses, decimal availableFunds, int registrationPayers, decimal registrationTotal, int annualPayers, decimal annualTotal, decimal dayongTotal) FinancialSummary()
	{
		using SqliteConnection cn = Open(); using SqliteCommand cmd = cn.CreateCommand();
		cmd.CommandText = @"SELECT
			COALESCE(SUM(P.Amount),0),
			COUNT(DISTINCT CASE WHEN C.Type='Registration Fee' AND P.Amount>=C.ExpectedAmount THEN P.MemberId END),
			COALESCE(SUM(CASE WHEN C.Type='Registration Fee' THEN P.Amount ELSE 0 END),0),
			COUNT(DISTINCT CASE WHEN C.Type='Annual Dues' AND P.Amount>=C.ExpectedAmount THEN P.MemberId END),
			COALESCE(SUM(CASE WHEN C.Type='Annual Dues' THEN P.Amount ELSE 0 END),0),
			COALESCE(SUM(CASE WHEN C.Type='Dayong' THEN P.Amount ELSE 0 END),0)
			FROM Payments P INNER JOIN CollectionCycles C ON C.Id=P.CycleId";
		using SqliteDataReader reader = cmd.ExecuteReader(); reader.Read();
		decimal total = reader.GetDecimal(0); int registrationPayers = reader.GetInt32(1); decimal registrationTotal = reader.GetDecimal(2);
		int annualPayers = reader.GetInt32(3); decimal annualTotal = reader.GetDecimal(4); decimal dayongTotal = reader.GetDecimal(5); reader.Close();
		using SqliteCommand expenses = cn.CreateCommand(); expenses.CommandText = "SELECT COALESCE(SUM(Amount),0) FROM Disbursements";
		decimal totalExpenses = Convert.ToDecimal(expenses.ExecuteScalar());
		return (total, totalExpenses, total - totalExpenses, registrationPayers, registrationTotal, annualPayers, annualTotal, dayongTotal);
	}

	public void Backup(string destination)
	{
		File.Copy(DatabasePath, destination, overwrite: true);
	}

	public List<ComplianceRow> GetComplianceRows(string council = "All Councils")
	{
		List<ComplianceRow> list = new List<ComplianceRow>();
		List<CollectionCycle> cycles = GetCycles();
		List<CollectionCycle> source = (from x in cycles
			where x.Type == "Annual Dues"
			orderby x.Id
			select x).ToList();
		List<CollectionCycle> source2 = (from x in cycles
			where x.Type == "Dayong"
			orderby x.Id
			select x).ToList();
		source2.OrderByDescending((CollectionCycle x) => x.Id).Take(2).ToList();
		Dictionary<long, Dictionary<long, PaymentRow>> annualPayments = source.ToDictionary((CollectionCycle x) => x.Id, (CollectionCycle x) => GetPaymentRows(x.Id, council).ToDictionary((PaymentRow p) => p.MemberId));
		Dictionary<long, Dictionary<long, PaymentRow>> dayongPayments = source2.ToDictionary((CollectionCycle x) => x.Id, (CollectionCycle x) => GetPaymentRows(x.Id, council).ToDictionary((PaymentRow p) => p.MemberId));
		foreach (Member m in GetMembers("", council))
		{
			if (m.MemberStatus.Equals("Deceased", StringComparison.OrdinalIgnoreCase))
			{
				list.Add(new ComplianceRow
				{
					MemberId = m.Id,
					MemberName = m.FullName,
					Council = m.Council,
					CurrentStatus = "Deceased",
					AnnualFee = "—",
					ConsecutiveMissedContributions = 0,
					UnpaidCycles = "—",
					GoodStanding = "—",
					Recommendation = "",
					StatusReason = "Deceased"
				});
				continue;
			}
			List<CollectionCycle> source3 = source2.Where((CollectionCycle x) => !m.StartCycleId.HasValue || x.Id >= m.StartCycleId.Value).ToList();
			List<CollectionCycle> list2 = source3.OrderByDescending((CollectionCycle x) => x.Id).Take(2).ToList();
			int? num = m.RegistrationDate?.Year;
			int firstAnnualYear = ((num >= 2027) ? (num.Value + 1) : 2027);
			List<CollectionCycle> source4 = source.Where(delegate(CollectionCycle x)
			{
				int? num2 = AnnualYear(x.Name);
				if (num2.HasValue)
				{
					int valueOrDefault = num2.GetValueOrDefault();
					if (valueOrDefault >= firstAnnualYear)
					{
						return valueOrDefault <= DateTime.Today.Year;
					}
				}
				return false;
			}).ToList();
			CollectionCycle collectionCycle = source4.FirstOrDefault((CollectionCycle x) => AnnualYear(x.Name) == DateTime.Today.Year);
			bool flag = DateTime.Today.Year >= firstAnnualYear;
			PaymentRow value;
			bool flag2 = !flag || (collectionCycle != null && annualPayments[collectionCycle.Id].TryGetValue(m.Id, out value) && value.Paid >= value.Expected);
			PaymentRow value2;
			List<string> list3 = (from x in source4
				where !annualPayments[x.Id].TryGetValue(m.Id, out value2) || value2.Paid < value2.Expected
				select x.Name).ToList();
			List<string> list4 = (from x in source3
				where !dayongPayments[x.Id].TryGetValue(m.Id, out value2) || value2.Paid < value2.Expected
				select x.Name).ToList();
			List<string> list5 = (from x in list2
				where !dayongPayments[x.Id].TryGetValue(m.Id, out value2) || value2.Paid < value2.Expected
				select x.Name).ToList();
			int count = list5.Count;
			string recommendation = "No action";
			List<string> list6 = new List<string>();
			if (DateTime.Today.Year < 2027)
			{
				list6.Add("Strict annual-dues compliance begins in 2027; earlier annual dues do not affect compliance status.");
			}
			else if (num == DateTime.Today.Year && num >= 2027)
			{
				list6.Add($"The ₱100 registration fee covers annual dues for {num}; the first separate annual due is {firstAnnualYear} under the 2027 implementation rule for Sections 4A-B.");
			}
			else if (flag && collectionCycle == null)
			{
				list6.Add($"No {DateTime.Today.Year} annual-dues cycle has been created; compliance cannot yet be confirmed (Sections 4B and 10).");
			}
			else if (!flag2 && collectionCycle != null)
			{
				list6.Add($"Has not fully paid '{collectionCycle.Name}' (₱{collectionCycle.ExpectedAmount:N2}), required by Sections 4B and 9A.");
				if (DateTime.Today >= new DateTime(DateTime.Today.Year, 2, 1))
				{
					list6.Add("Group Accident Insurance coverage is lost after one month from January 1 under Section 4D.");
				}
				if (DateTime.Today >= new DateTime(DateTime.Today.Year, 3, 1))
				{
					list6.Add("Subject to Inactive status after two months from January 1 under Section 4E.");
				}
			}
			if (list3.Count > 0)
			{
				list6.Add("Unpaid annual-fee cycle(s): " + string.Join(", ", list3) + " (Sections 4B and 9A).");
			}
			if (list4.Count > 0)
			{
				list6.Add("Unpaid mortuary contribution cycle(s): " + string.Join(", ", list4) + " (Sections 5B and 9B).");
			}
			if (list2.Count >= 2 && count >= 2)
			{
				recommendation = "Subject for Board expulsion review";
				list6.Add("The two latest consecutive unpaid mortuary contributions are: " + string.Join(", ", list5) + ". Section 5C provides for expulsion, while Sections 13A-B identify non-payment as grounds. Board action/resolution must still be recorded.");
			}
			else if (m.MemberStatus == "Active" && flag && !flag2 && collectionCycle != null && DateTime.Today >= new DateTime(DateTime.Today.Year, 3, 1))
			{
				recommendation = "Review for Inactive status";
			}
			if (m.MemberStatus == "Inactive")
			{
				list6.Add("Officially recorded Inactive. Reinstatement requires full payment of arrears under Section 14A.");
			}
			if (m.MemberStatus == "Expelled")
			{
				list6.Add("Officially recorded Expelled. Reinstatement requires a request through the Council Grand Knight, Board evaluation and approval, and full payment of arrears under Section 14B.");
			}
			if (m.MemberStatus == "Deceased")
			{
				list6.Add("Officially recorded Deceased; verify benefits and any qualified automatic associate membership under Sections 6 and 8.");
			}
			bool flag3 = m.MemberStatus == "Active" && flag2 && list4.Count == 0;
			if (flag3)
			{
				list6.Add("Compliant with annual fees and recorded mortuary contributions; member is in good standing under Section 10.");
			}
			if (!string.IsNullOrWhiteSpace(m.Remarks))
			{
				list6.Add("Officer remarks: " + m.Remarks);
			}
			string annualFee = ((DateTime.Today.Year < 2027) ? "Starts 2027" : ((num == DateTime.Today.Year && num >= 2027) ? $"Registration covers {num}" : ((list3.Count == 0) ? "None" : string.Join(", ", list3.Select(AnnualYearLabel)))));
			list.Add(new ComplianceRow
			{
				MemberId = m.Id,
				MemberName = m.FullName,
				Council = m.Council,
				CurrentStatus = m.MemberStatus,
				AnnualFee = annualFee,
				ConsecutiveMissedContributions = count,
				UnpaidCycles = ((list4.Count == 0) ? "None" : string.Join(", ", list4)),
				GoodStanding = (flag3 ? "Yes" : "No"),
				Recommendation = recommendation,
				StatusReason = string.Join(" ", list6)
			});
		}
		return (from x in list
			orderby x.Council, x.MemberName
			select x).ToList();
	}

	private static string AnnualYearLabel(string cycleName)
	{
		Match match = Regex.Match(cycleName, "\\b(20\\d{2})\\b");
		if (!match.Success)
		{
			return cycleName;
		}
		return match.Groups[1].Value;
	}

	private static int? AnnualYear(string cycleName)
	{
		Match match = Regex.Match(cycleName, "\\b(20\\d{2})\\b");
		if (!match.Success || !int.TryParse(match.Groups[1].Value, out var result))
		{
			return null;
		}
		return result;
	}
}
