using SQLite;

namespace KCLDA.DayongManager.Mobile;

public class MobileUser { [PrimaryKey, AutoIncrement] public int Id { get; set; } public string Username { get; set; } = ""; public string PasswordHash { get; set; } = ""; }
public class MobileMember
{
	[PrimaryKey, AutoIncrement] public int Id { get; set; }
	public string LastName { get; set; } = ""; public string FirstName { get; set; } = ""; public string Council { get; set; } = "";
	public DateTime RegistrationDate { get; set; } = DateTime.Today; public int? StartCycleId { get; set; }
	public string Status { get; set; } = "Active"; public DateTime? DateOfDeath { get; set; }
	[Ignore] public string FullName => $"{LastName}, {FirstName}";
}
public class MobileCycle
{
	[PrimaryKey, AutoIncrement] public int Id { get; set; } public string Name { get; set; } = "";
	public string Type { get; set; } = "Dayong"; public decimal ExpectedAmount { get; set; }
	public DateTime? StartDate { get; set; } public DateTime? DueDate { get; set; } public bool Active { get; set; } = true;
	public override string ToString() => Name;
}
public class MobilePayment
{
	[PrimaryKey, AutoIncrement] public int Id { get; set; } [Indexed] public int MemberId { get; set; } [Indexed] public int CycleId { get; set; }
	public decimal Amount { get; set; } public DateTime DatePaid { get; set; } public string ReceiptNumber { get; set; } = "";
}
public record MobileDue(MobileCycle Cycle, decimal Paid) { public decimal Balance => Math.Max(0, Cycle.ExpectedAmount - Paid); public string Status => Paid >= Cycle.ExpectedAmount ? "Paid" : Paid > 0 ? "Partial" : "Unpaid"; }
