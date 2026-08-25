using System;
using System.Linq;

namespace DayongManager;

public sealed class Member
{
	public long Id { get; set; }

	public string LastName { get; set; } = "";

	public string FirstName { get; set; } = "";

	public string MiddleName { get; set; } = "";

	public string Address { get; set; } = "";

	public DateTime? BirthDate { get; set; }

	public string Council { get; set; } = "";

	public bool Active { get; set; } = true;

	public string MembershipType { get; set; } = "Brother Knight";

	public string SponsorName { get; set; } = "";

	public string ContactNumber { get; set; } = "";

	public string BeneficiaryName { get; set; } = "";

	public string BeneficiaryContact { get; set; } = "";

	public bool IsFourthDegree { get; set; }

	public string MemberStatus { get; set; } = "Active";

	public string Remarks { get; set; } = "";

	public DateTime? RegistrationDate { get; set; }

	public long? StartCycleId { get; set; }

	public string ClaimedBenefits { get; set; } = "";

	public DateTime? ServiceDate { get; set; }

	public DateTime? ClaimReceivedDate { get; set; }

	public string ClaimReceivedBy { get; set; } = "";

	public string FullName => string.Join(" ", new string[3] { FirstName, MiddleName, LastName }.Where((string x) => !string.IsNullOrWhiteSpace(x)));
}
