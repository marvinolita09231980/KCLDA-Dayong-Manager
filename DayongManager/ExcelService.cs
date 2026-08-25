using System;
using System.Linq;
using ClosedXML.Excel;

namespace DayongManager;

public static class ExcelService
{
	public static (int members, int payments) Import(string path, DatabaseService db)
	{
		using XLWorkbook xLWorkbook = new XLWorkbook(path);
		int num = 0;
		int num2 = 0;
		foreach (IXLWorksheet item in xLWorkbook.Worksheets.Where((IXLWorksheet s) => s.Name != "MEMBERINFO" && s.Name != "FUNDS"))
		{
			string council = item.Name.Trim();
			foreach (IXLRow item2 in from r in item.RowsUsed()
				where r.RowNumber() >= 5
				select r)
			{
				string last = Text(item2.Cell(2));
				string first = Text(item2.Cell(3));
				if (last == "" || first == "")
				{
					continue;
				}
				Member member = new Member
				{
					LastName = last,
					FirstName = first,
					MiddleName = Text(item2.Cell(4)),
					Address = Text(item2.Cell(5)),
					BirthDate = Date(item2.Cell(6)),
					Council = Text(item2.Cell(7))
				};
				if (member.Council == "")
				{
					member.Council = council;
				}
				else
				{
					member.Council = member.Council.Replace(".0", "");
				}
				long memberId;
				try
				{
					memberId = db.SaveMember(member);
					num++;
				}
				catch
				{
					Member member2 = db.GetMembers(last, member.Council).FirstOrDefault((Member x) => x.LastName.Equals(last, StringComparison.OrdinalIgnoreCase) && x.FirstName.Equals(first, StringComparison.OrdinalIgnoreCase));
					if (member2 == null)
					{
						continue;
					}
					memberId = member2.Id;
				}
				(string, string, int, int, decimal)[] array = new(string, string, int, int, decimal)[4]
				{
					("SK Felix Magalona", "Dayong", 8, 9, 200m),
					("Second Round Collection", "Dayong", 10, 11, 200m),
					("CY 2025 Registration Fee", "Registration Fee", 12, 13, 100m),
					("CY 2026 Registration Fee", "Registration Fee", 14, 15, 100m)
				};
				for (int num3 = 0; num3 < array.Length; num3++)
				{
					(string, string, int, int, decimal) z = array[num3];
					var (date, num4) = PaymentPair(item2.Cell(z.Item3), item2.Cell(z.Item4));
					if (!(num4 <= 0m) || date.HasValue)
					{
						long cycleId = db.GetCycles().FirstOrDefault((CollectionCycle x) => x.Name == z.Item1)?.Id ?? db.SaveCycle(new CollectionCycle
						{
							Name = z.Item1,
							Type = z.Item2,
							ExpectedAmount = z.Item5
						});
						db.SavePayment(memberId, cycleId, (num4 > 0m) ? num4 : z.Item5, date);
						num2++;
					}
				}
			}
		}
		return (members: num, payments: num2);
	}

	private static string Text(IXLCell c)
	{
		return c.GetFormattedString().Trim();
	}

	private static DateTime? Date(IXLCell c)
	{
		if (!c.TryGetValue<DateTime>(out var value))
		{
			return null;
		}
		return value;
	}

	private static decimal Money(IXLCell c)
	{
		if (!c.TryGetValue<decimal>(out var value))
		{
			return 0m;
		}
		return value;
	}

	private static (DateTime? date, decimal amount) PaymentPair(IXLCell a, IXLCell b)
	{
		DateTime? item = Date(a) ?? Date(b);
		decimal num = default(decimal);
		if (!Date(a).HasValue)
		{
			num = Money(a);
		}
		if (num <= 0m && !Date(b).HasValue)
		{
			num = Money(b);
		}
		return (date: item, amount: num);
	}

	public static void Export(string path, DatabaseService db)
	{
		using XLWorkbook xLWorkbook = new XLWorkbook();
		IXLWorksheet iXLWorksheet = xLWorkbook.AddWorksheet("Members");
		iXLWorksheet.Cell(1, 1).InsertTable(from m in db.GetMembers()
			select new
			{
				Id = m.Id,
				LastName = m.LastName,
				FirstName = m.FirstName,
				MiddleName = m.MiddleName,
				MembershipType = m.MembershipType,
				SponsorName = m.SponsorName,
				Council = m.Council,
				ContactNumber = m.ContactNumber,
				Address = m.Address,
				BirthDate = m.BirthDate?.ToString("yyyy-MM-dd"),
				RegistrationDate = m.RegistrationDate?.ToString("yyyy-MM-dd"),
				IsFourthDegree = m.IsFourthDegree,
				BeneficiaryName = m.BeneficiaryName,
				BeneficiaryContact = m.BeneficiaryContact,
				Status = m.MemberStatus,
				Remarks = m.Remarks
			});
		iXLWorksheet.Columns().AdjustToContents();
		IXLWorksheet iXLWorksheet2 = xLWorkbook.AddWorksheet("Payments");
		iXLWorksheet2.Cell(1, 1).Value = "Cycle";
		iXLWorksheet2.Cell(1, 2).Value = "Member";
		iXLWorksheet2.Cell(1, 3).Value = "Council";
		iXLWorksheet2.Cell(1, 4).Value = "Expected";
		iXLWorksheet2.Cell(1, 5).Value = "Paid";
		iXLWorksheet2.Cell(1, 6).Value = "Date Paid";
		iXLWorksheet2.Cell(1, 7).Value = "Status";
		int num = 2;
		foreach (CollectionCycle cycle in db.GetCycles())
		{
			foreach (PaymentRow paymentRow in db.GetPaymentRows(cycle.Id))
			{
				iXLWorksheet2.Cell(num, 1).Value = cycle.Name;
				iXLWorksheet2.Cell(num, 2).Value = paymentRow.MemberName;
				iXLWorksheet2.Cell(num, 3).Value = paymentRow.Council;
				iXLWorksheet2.Cell(num, 4).Value = paymentRow.Expected;
				iXLWorksheet2.Cell(num, 5).Value = paymentRow.Paid;
				if (paymentRow.DatePaid.HasValue)
				{
					iXLWorksheet2.Cell(num, 6).Value = paymentRow.DatePaid.Value;
				}
				iXLWorksheet2.Cell(num, 7).Value = paymentRow.Status;
				num++;
			}
		}
		iXLWorksheet2.Range(1, 1, Math.Max(1, num - 1), 7).CreateTable();
		iXLWorksheet2.Columns().AdjustToContents();
		iXLWorksheet2.Column(4).Style.NumberFormat.Format = "₱#,##0.00";
		iXLWorksheet2.Column(5).Style.NumberFormat.Format = "₱#,##0.00";
		xLWorkbook.SaveAs(path);
		IXLWorksheet iXLWorksheet3 = xLWorkbook.AddWorksheet("Good Standing Review");
		iXLWorksheet3.Cell(1, 1).InsertTable(from x in db.GetComplianceRows()
			select new { x.Council, x.MemberName, x.CurrentStatus, x.AnnualFee, x.ConsecutiveMissedContributions, x.UnpaidCycles, x.GoodStanding, x.Recommendation, x.StatusReason });
		iXLWorksheet3.Columns().AdjustToContents();
		iXLWorksheet3.Column(9).Width = 80.0;
		iXLWorksheet3.Column(9).Style.Alignment.WrapText = true;
		xLWorkbook.SaveAs(path);
	}
}
