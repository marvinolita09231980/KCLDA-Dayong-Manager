using System;

namespace DayongManager;

public sealed class CollectionCycle
{
	public long Id { get; set; }

	public string Name { get; set; } = "";

	public string Type { get; set; } = "Dayong";

	public decimal ExpectedAmount { get; set; }

	public DateTime? DueDate { get; set; }

	public bool Active { get; set; } = true;
}
