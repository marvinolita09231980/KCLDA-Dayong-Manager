using System;

namespace DayongManager;

public sealed class BankTransaction
{
	public long Id { get; set; }
	public DateTime TransactionDate { get; set; } = DateTime.Today;
	public string TransactionType { get; set; } = "Deposit";
	public decimal Amount { get; set; }
	public string ReferenceNumber { get; set; } = "";
	public string Description { get; set; } = "";
	public string RecordedBy { get; set; } = "";
}
