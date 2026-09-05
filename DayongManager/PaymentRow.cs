using System;

namespace DayongManager;

public sealed class PaymentRow
{
	public long PaymentId { get; set; }

	public long MemberId { get; set; }

	public string MemberName { get; set; } = "";

	public string Council { get; set; } = "";

	public decimal Expected { get; set; }

	public decimal Paid { get; set; }

	public DateTime? DatePaid { get; set; }

	public string ReceiptNumber { get; set; } = "";

	public string Status
	{
		get
		{
			if (!(Paid >= Expected) || !(Expected > 0m))
			{
				if (!(Paid > 0m))
				{
					return "Unpaid";
				}
				return "Partial";
			}
			return "Paid";
		}
	}
}
