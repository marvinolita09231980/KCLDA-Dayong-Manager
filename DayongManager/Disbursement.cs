using System;

namespace DayongManager;

public sealed class Disbursement
{
	public long Id { get; set; }
	public DateTime DisbursementDate { get; set; } = DateTime.Today;
	public string VoucherNumber { get; set; } = "";
	public string Payee { get; set; } = "";
	public string Category { get; set; } = "Other Expense";
	public string Particulars { get; set; } = "";
	public decimal Amount { get; set; }
	public string RecordedBy { get; set; } = "";
}
