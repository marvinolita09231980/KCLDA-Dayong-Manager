namespace DayongManager;

public sealed class ComplianceRow
{
	public long MemberId { get; set; }

	public string MemberName { get; set; } = "";

	public string Council { get; set; } = "";

	public string CurrentStatus { get; set; } = "";

	public string AnnualFee { get; set; } = "";

	public int ConsecutiveMissedContributions { get; set; }

	public string GoodStanding { get; set; } = "";

	public string Recommendation { get; set; } = "";

	public string StatusReason { get; set; } = "";

	public string UnpaidCycles { get; set; } = "";
}
