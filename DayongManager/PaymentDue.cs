namespace DayongManager;

public sealed class PaymentDue
{
	public long CycleId { get; set; }
	public string CycleName { get; set; } = "";
	public string Type { get; set; } = "";
	public decimal Required { get; set; }
	public decimal PreviouslyPaid { get; set; }
	public decimal Balance => System.Math.Max(0m, Required - PreviouslyPaid);
	public bool IsSelectedCycle { get; set; }
}
