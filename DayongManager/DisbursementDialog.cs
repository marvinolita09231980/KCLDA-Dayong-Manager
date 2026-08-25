using System;
using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class DisbursementDialog : Form
{
	private readonly DateTimePicker expenseDate = new DateTimePicker { Format = DateTimePickerFormat.Long };
	private readonly TextBox voucher = new TextBox();
	private readonly TextBox payee = new TextBox();
	private readonly ComboBox category = new ComboBox { DropDownStyle = ComboBoxStyle.DropDown };
	private readonly TextBox particulars = new TextBox { Multiline = true, ScrollBars = ScrollBars.Vertical };
	private readonly NumericUpDown amount = new NumericUpDown { DecimalPlaces = 2, Maximum = 1000000000m, ThousandsSeparator = true };
	public Disbursement Value { get; private set; }

	public DisbursementDialog(Disbursement? existing = null)
	{
		Value = existing ?? new Disbursement();
		Text = existing == null ? "Record Expense" : "Edit Expense";
		ClientSize = new Size(650, 550); StartPosition = FormStartPosition.CenterParent;
		FormBorderStyle = FormBorderStyle.FixedDialog; MaximizeBox = false; MinimizeBox = false;
		Font = new Font("Segoe UI", 11f); BackColor = Color.FromArgb(248, 249, 252);
		category.Items.AddRange(new object[] { "Benefits and Claims", "Bank Charges", "Flowers and Wreath", "Mass and Necrological Service", "Office Supplies", "Transportation", "Meeting Expense", "Other Expense" });
		expenseDate.Value = Value.DisbursementDate; voucher.Text = Value.VoucherNumber; payee.Text = Value.Payee;
		category.Text = Value.Category; particulars.Text = Value.Particulars; amount.Value = Math.Min(amount.Maximum, Math.Max(0, Value.Amount));

		TableLayoutPanel p = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(28), ColumnCount = 2, RowCount = 7 };
		p.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 185)); p.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
		Add(p, "Disbursement date", expenseDate, 0); Add(p, "Voucher / check no.", voucher, 1); Add(p, "Payee / recipient", payee, 2);
		Add(p, "Expense category", category, 3); Add(p, "Particulars", particulars, 4); Add(p, "Amount", amount, 5);
		FlowLayoutPanel actions = new FlowLayoutPanel { Dock = DockStyle.Fill, FlowDirection = FlowDirection.RightToLeft };
		Button save = new Button { Text = "Save Expense", Width = 145, Height = 42, BackColor = Color.FromArgb(0, 47, 95), ForeColor = Color.White, FlatStyle = FlatStyle.Flat };
		Button cancel = new Button { Text = "Cancel", Width = 110, Height = 42, DialogResult = DialogResult.Cancel };
		actions.Controls.Add(save); actions.Controls.Add(cancel); p.Controls.Add(actions, 1, 6); Controls.Add(p); AcceptButton = save; CancelButton = cancel;
		save.Click += delegate
		{
			if (string.IsNullOrWhiteSpace(payee.Text)) { MessageBox.Show("Enter the payee or recipient."); return; }
			if (string.IsNullOrWhiteSpace(category.Text)) { MessageBox.Show("Select or enter an expense category."); return; }
			if (string.IsNullOrWhiteSpace(particulars.Text)) { MessageBox.Show("Enter the expense particulars."); return; }
			if (amount.Value <= 0) { MessageBox.Show("Enter an amount greater than zero."); return; }
			Value.DisbursementDate = expenseDate.Value.Date; Value.VoucherNumber = voucher.Text.Trim(); Value.Payee = payee.Text.Trim();
			Value.Category = category.Text.Trim(); Value.Particulars = particulars.Text.Trim(); Value.Amount = amount.Value; DialogResult = DialogResult.OK;
		};
	}

	private static void Add(TableLayoutPanel p, string label, Control field, int row)
	{ p.Controls.Add(new Label { Text = label, AutoSize = true, Anchor = AnchorStyles.Left }, 0, row); field.Dock = DockStyle.Fill; p.Controls.Add(field, 1, row); }
}
