using System;
using System.Collections.Generic;
using System.Linq;

namespace DayongManager;

public sealed class UserAccess
{
	public long Id { get; set; }
	public string Username { get; set; } = "";
	public string DisplayName { get; set; } = "";
	public bool Active { get; set; } = true;
	public bool IsAdmin { get; set; }
	public string PermissionsText { get; set; } = "";
	public HashSet<string> Permissions => PermissionsText.Split('|', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries).ToHashSet(StringComparer.OrdinalIgnoreCase);
	public bool Can(string permission) => IsAdmin || Permissions.Contains(permission);
}

public static class AppPermissions
{
	public static readonly (string Key, string Label)[] All =
	{
		("ViewDashboard", "Dashboard — View"),
		("ViewMembers", "Members — View"), ("AddMembers", "Members — Add"), ("EditMembers", "Members — Edit"), ("DeleteMembers", "Members — Delete"),
		("ViewCollections", "Collections & Dues — View"), ("RecordPayments", "Collections & Dues — Record payment"), ("PrintTreasurerReport", "Collections & Dues — Treasurer's report"),
		("ViewCompliance", "Good Standing & Compliance — View"), ("ReviewMemberStatus", "Good Standing & Compliance — Review member status"),
		("ViewLedger", "Bank Ledger — View"), ("AddLedger", "Bank Ledger — Record deposit or withdrawal"), ("EditLedger", "Bank Ledger — Edit transaction"), ("DeleteLedger", "Bank Ledger — Delete transaction"), ("PrintLedger", "Bank Ledger — Print report"),
		("ViewDisbursements", "Disbursement Ledger — View"), ("AddDisbursements", "Disbursement Ledger — Record expense"), ("EditDisbursements", "Disbursement Ledger — Edit expense"), ("DeleteDisbursements", "Disbursement Ledger — Delete expense"), ("PrintDisbursements", "Disbursement Ledger — Print report"),
		("ViewCycles", "Collection Cycles — View"), ("AddCycles", "Collection Cycles — Add"), ("EditCycles", "Collection Cycles — Edit"), ("DeleteCycles", "Collection Cycles — Delete"),
		("ViewTools", "Import, Export & Backup — View"), ("ImportExcel", "Tools — Import Excel"), ("ExportExcel", "Tools — Export Excel"),
		("BackupDatabase", "Tools — Back up database"), ("OpenDataFolder", "Tools — Open data folder"), ("OpenBylaws", "Tools — Open by-laws"),
		("ManageUsers", "Administration — Create and manage users")
	};
}
