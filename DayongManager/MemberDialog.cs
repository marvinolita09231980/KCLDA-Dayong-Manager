using System;
using System.Collections.Generic;
using System.Drawing;
using System.Linq;
using System.Windows.Forms;

namespace DayongManager;

public sealed class MemberDialog : Form
{
	private readonly TextBox last = new TextBox();

	private readonly TextBox first = new TextBox();

	private readonly TextBox middle = new TextBox();

	private readonly TextBox address = new TextBox();

	private readonly TextBox council = new TextBox();

	private readonly TextBox sponsor = new TextBox();

	private readonly TextBox contact = new TextBox();

	private readonly TextBox beneficiary = new TextBox();

	private readonly TextBox beneficiaryContact = new TextBox();

	private readonly TextBox remarks = new TextBox();

	private readonly DateTimePicker birth = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly DateTimePicker registered = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly CheckBox fourthDegree = new CheckBox
	{
		Text = "Fourth-Degree member"
	};

	private readonly ComboBox memberType = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList
	};

	private readonly ComboBox status = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList
	};

	private readonly ComboBox startCycle = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList
	};

	private readonly CheckedListBox claimedBenefits = new CheckedListBox
	{
		CheckOnClick = true,
		Height = 125
	};

	private readonly DateTimePicker serviceDate = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly DateTimePicker claimReceivedDate = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly TextBox claimReceivedBy = new TextBox();

	public Member Value { get; private set; }

	public MemberDialog(Member? m = null, List<CollectionCycle>? availableCycles = null)
	{
		Value = m ?? new Member();
		Text = ((m == null) ? "Add Member" : "Edit Member");
		base.Width = 700;
		base.Height = 940;
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(248, 249, 252);
		base.StartPosition = FormStartPosition.CenterParent;
		base.FormBorderStyle = FormBorderStyle.FixedDialog;
		base.MaximizeBox = false;
		base.MinimizeBox = false;
		AutoScroll = true;
		if (m == null)
		{
			registered.Checked = true;
			registered.Value = DateTime.Today;
		}
		memberType.Items.AddRange(new object[6] { "Brother Knight", "Wife", "Parent (Mother)", "Widow Mother-in-Law", "Daughter", "Associate Member" });
		status.Items.AddRange(new object[4] { "Active", "Inactive", "Expelled", "Deceased" });
		claimedBenefits.Items.AddRange(new object[7] { "Cash assistance", "Sword", "Chalice", "Mass card", "Wreath", "Necrological service", "Chaplet" });
		memberType.SelectedIndex = 0;
		status.SelectedIndex = 0;
		startCycle.DisplayMember = "Name";
		startCycle.ValueMember = "Id";
		foreach (CollectionCycle item in (availableCycles ?? new List<CollectionCycle>()).FindAll((CollectionCycle x) => x.Type == "Dayong"))
		{
			startCycle.Items.Add(item);
		}
		if (startCycle.Items.Count > 0)
		{
			startCycle.SelectedIndex = 0;
		}
		TableLayoutPanel tableLayoutPanel = new TableLayoutPanel
		{
			Dock = DockStyle.Fill,
			Padding = new Padding(26),
			ColumnCount = 2,
			RowCount = 21,
			AutoScroll = true
		};
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 180f));
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
		Add(tableLayoutPanel, "Last name", last, 0);
		Add(tableLayoutPanel, "First name", first, 1);
		Add(tableLayoutPanel, "Middle name", middle, 2);
		Add(tableLayoutPanel, "Membership type", memberType, 3);
		Add(tableLayoutPanel, "Sponsoring Knight", sponsor, 4);
		Add(tableLayoutPanel, "Council", council, 5);
		Add(tableLayoutPanel, "Contact number", contact, 6);
		Add(tableLayoutPanel, "Address", address, 7);
		Add(tableLayoutPanel, "Birthdate", birth, 8);
		Add(tableLayoutPanel, "Registration date", registered, 9);
		Add(tableLayoutPanel, "Membership start cycle", startCycle, 10);
		Add(tableLayoutPanel, "Beneficiary", beneficiary, 11);
		Add(tableLayoutPanel, "Beneficiary contact", beneficiaryContact, 12);
		tableLayoutPanel.Controls.Add(fourthDegree, 1, 13);
		Add(tableLayoutPanel, "Official status", status, 14);
		Add(tableLayoutPanel, "Status remarks", remarks, 15);
		remarks.Multiline = true;
		remarks.ScrollBars = ScrollBars.Vertical;
		Add(tableLayoutPanel, "Benefits claimed", claimedBenefits, 16);
		Add(tableLayoutPanel, "Service date", serviceDate, 17);
		Add(tableLayoutPanel, "Claim received date", claimReceivedDate, 18);
		Add(tableLayoutPanel, "Received by / beneficiary", claimReceivedBy, 19);
		FlowLayoutPanel flowLayoutPanel = new FlowLayoutPanel
		{
			Dock = DockStyle.Fill,
			FlowDirection = FlowDirection.RightToLeft
		};
		Button button = new Button
		{
			Text = "Save",
			Width = 110,
			Height = 40,
			DialogResult = DialogResult.None,
			BackColor = Color.FromArgb(0, 47, 95),
			ForeColor = Color.White,
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f)
		};
		Button button2 = new Button
		{
			Text = "Cancel",
			Width = 110,
			Height = 40,
			DialogResult = DialogResult.Cancel,
			BackColor = Color.White,
			ForeColor = Color.FromArgb(0, 47, 95),
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f)
		};
		flowLayoutPanel.Controls.Add(button);
		flowLayoutPanel.Controls.Add(button2);
		tableLayoutPanel.Controls.Add(flowLayoutPanel, 1, 20);
		base.Controls.Add(tableLayoutPanel);
		base.AcceptButton = button;
		base.CancelButton = button2;
		if (m != null)
		{
			last.Text = m.LastName;
			first.Text = m.FirstName;
			middle.Text = m.MiddleName;
			address.Text = m.Address;
			council.Text = m.Council;
			birth.Checked = m.BirthDate.HasValue;
			if (m.BirthDate.HasValue)
			{
				birth.Value = m.BirthDate.Value;
			}
			registered.Checked = m.RegistrationDate.HasValue;
			if (m.RegistrationDate.HasValue)
			{
				registered.Value = m.RegistrationDate.Value;
			}
			memberType.SelectedItem = m.MembershipType;
			sponsor.Text = m.SponsorName;
			contact.Text = m.ContactNumber;
			beneficiary.Text = m.BeneficiaryName;
			beneficiaryContact.Text = m.BeneficiaryContact;
			fourthDegree.Checked = m.IsFourthDegree;
			status.SelectedItem = m.MemberStatus;
			remarks.Text = m.Remarks;
			string[] array = m.ClaimedBenefits.Split(',', StringSplitOptions.RemoveEmptyEntries | StringSplitOptions.TrimEntries);
			foreach (string value in array)
			{
				int num2 = claimedBenefits.Items.IndexOf(value);
				if (num2 >= 0)
				{
					claimedBenefits.SetItemChecked(num2, value: true);
				}
			}
			serviceDate.Checked = m.ServiceDate.HasValue;
			if (m.ServiceDate.HasValue)
			{
				serviceDate.Value = m.ServiceDate.Value;
			}
			claimReceivedDate.Checked = m.ClaimReceivedDate.HasValue;
			if (m.ClaimReceivedDate.HasValue)
			{
				claimReceivedDate.Value = m.ClaimReceivedDate.Value;
			}
			claimReceivedBy.Text = m.ClaimReceivedBy;
			if (m.StartCycleId.HasValue)
			{
				foreach (object item2 in startCycle.Items)
				{
					if (item2 is CollectionCycle { Id: var id } && id == m.StartCycleId.Value)
					{
						startCycle.SelectedItem = item2;
						break;
					}
				}
			}
		}
		status.SelectedIndexChanged += delegate
		{
			UpdateClaimFields();
		};
		UpdateClaimFields();
		button.Click += delegate
		{
			if (string.IsNullOrWhiteSpace(last.Text) || string.IsNullOrWhiteSpace(first.Text) || string.IsNullOrWhiteSpace(council.Text))
			{
				MessageBox.Show("Last name, first name, and council are required.");
			}
			else if (startCycle.Items.Count > 0 && startCycle.SelectedItem == null)
			{
				MessageBox.Show("Select the cycle when this member started membership.");
			}
			else if (memberType.Text == "Associate Member" && string.IsNullOrWhiteSpace(beneficiary.Text))
			{
				MessageBox.Show("An associate member must appoint a beneficiary under Section 8.");
			}
			else
			{
				Value.LastName = last.Text;
				Value.FirstName = first.Text;
				Value.MiddleName = middle.Text;
				Value.Address = address.Text;
				Value.Council = council.Text;
				Value.BirthDate = (birth.Checked ? new DateTime?(birth.Value.Date) : ((DateTime?)null));
				Value.RegistrationDate = (registered.Checked ? new DateTime?(registered.Value.Date) : ((DateTime?)null));
				Value.MembershipType = memberType.Text;
				Value.SponsorName = sponsor.Text;
				Value.ContactNumber = contact.Text;
				Value.BeneficiaryName = beneficiary.Text;
				Value.BeneficiaryContact = beneficiaryContact.Text;
				Value.IsFourthDegree = fourthDegree.Checked;
				Value.MemberStatus = status.Text;
				Value.Remarks = remarks.Text;
				Value.StartCycleId = (startCycle.SelectedItem as CollectionCycle)?.Id;
				Value.ClaimedBenefits = string.Join(", ", from object x in claimedBenefits.CheckedItems
					select x.ToString());
				Value.ServiceDate = (serviceDate.Checked ? new DateTime?(serviceDate.Value.Date) : ((DateTime?)null));
				Value.ClaimReceivedDate = (claimReceivedDate.Checked ? new DateTime?(claimReceivedDate.Value.Date) : ((DateTime?)null));
				Value.ClaimReceivedBy = claimReceivedBy.Text;
				Value.Active = status.Text == "Active";
				base.DialogResult = DialogResult.OK;
			}
		};
		void UpdateClaimFields()
		{
			bool enabled = status.Text == "Deceased";
			claimedBenefits.Enabled = enabled;
			serviceDate.Enabled = enabled;
			claimReceivedDate.Enabled = enabled;
			claimReceivedBy.Enabled = enabled;
		}
	}

	private static void Add(TableLayoutPanel p, string label, Control c, int row)
	{
		p.Controls.Add(new Label
		{
			Text = label,
			AutoSize = true,
			Anchor = AnchorStyles.Left
		}, 0, row);
		c.Dock = DockStyle.Fill;
		p.Controls.Add(c, 1, row);
	}
}
