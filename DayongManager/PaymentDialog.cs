using System;
using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class PaymentDialog : Form
{
	private readonly NumericUpDown amount = new NumericUpDown
	{
		Maximum = 100000m,
		DecimalPlaces = 2,
		ThousandsSeparator = true
	};

	private readonly DateTimePicker date = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	public decimal Amount => amount.Value;

	public DateTime? DatePaid
	{
		get
		{
			if (!date.Checked)
			{
				return null;
			}
			return date.Value.Date;
		}
	}

	public PaymentDialog(PaymentRow p)
	{
		Text = "Record Payment";
		base.Width = 520;
		base.Height = 300;
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(248, 249, 252);
		base.StartPosition = FormStartPosition.CenterParent;
		TableLayoutPanel tableLayoutPanel = new TableLayoutPanel
		{
			Dock = DockStyle.Fill,
			Padding = new Padding(26),
			ColumnCount = 2,
			RowCount = 4
		};
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 145f));
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
		tableLayoutPanel.Controls.Add(new Label
		{
			Text = p.MemberName,
			AutoSize = true,
			Font = new Font(Font, FontStyle.Bold)
		}, 0, 0);
		tableLayoutPanel.SetColumnSpan(tableLayoutPanel.GetControlFromPosition(0, 0), 2);
		Add(tableLayoutPanel, "Amount", amount, 1);
		Add(tableLayoutPanel, "Date paid", date, 2);
		amount.Value = ((p.Paid > 0m) ? p.Paid : p.Expected);
		date.Checked = true;
		date.Value = p.DatePaid ?? DateTime.Today;
		FlowLayoutPanel flowLayoutPanel = new FlowLayoutPanel
		{
			Dock = DockStyle.Fill,
			FlowDirection = FlowDirection.RightToLeft
		};
		Button button = new Button
		{
			Text = "Save",
			DialogResult = DialogResult.OK,
			Width = 110,
			Height = 40,
			BackColor = Color.FromArgb(0, 47, 95),
			ForeColor = Color.White,
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f)
		};
		Button button2 = new Button
		{
			Text = "Cancel",
			DialogResult = DialogResult.Cancel,
			Width = 110,
			Height = 40,
			BackColor = Color.White,
			ForeColor = Color.FromArgb(0, 47, 95),
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f)
		};
		flowLayoutPanel.Controls.Add(button);
		flowLayoutPanel.Controls.Add(button2);
		tableLayoutPanel.Controls.Add(flowLayoutPanel, 1, 3);
		base.Controls.Add(tableLayoutPanel);
		base.AcceptButton = button;
		base.CancelButton = button2;
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
