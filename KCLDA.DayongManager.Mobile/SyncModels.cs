namespace KCLDA.DayongManager.Mobile;

public sealed class LanSyncRequest { public List<LanSyncPayment> Payments { get; set; } = new(); }
public sealed class LanSyncSnapshot { public List<LanSyncMember> Members { get; set; } = new(); public List<LanSyncCycle> Cycles { get; set; } = new(); public List<LanSyncPayment> Payments { get; set; } = new(); }
public sealed class LanSyncMember { public long Id { get; set; } public string LastName { get; set; } = ""; public string FirstName { get; set; } = ""; public string Council { get; set; } = ""; public DateTime RegistrationDate { get; set; } public long? StartCycleId { get; set; } public string Status { get; set; } = "Active"; public DateTime? DateOfDeath { get; set; } }
public sealed class LanSyncCycle { public long Id { get; set; } public string Name { get; set; } = ""; public string Type { get; set; } = ""; public decimal ExpectedAmount { get; set; } public DateTime? StartDate { get; set; } public DateTime? DueDate { get; set; } public bool Active { get; set; } }
public sealed class LanSyncPayment { public long Id { get; set; } public long MemberId { get; set; } public long CycleId { get; set; } public decimal Amount { get; set; } public DateTime DatePaid { get; set; } public string ReceiptNumber { get; set; } = ""; }
