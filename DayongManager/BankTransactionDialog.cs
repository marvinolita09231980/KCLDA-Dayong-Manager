using System;
using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class BankTransactionDialog : Form
{
	private readonly DateTimePicker transactionDate = new DateTimePicker { Format = DateTimePickerFormat.Long };
	private readonly ComboBox transactionType = new ComboBox { DropDownStyle = ComboBoxStyle.DropDownList };
	private readonly NumericUpDown amount = new NumericUpDown { DecimalPlaces = 2, Maximum = 1000000000m, ThousandsSeparator = true };
	private readonly TextBox reference = new TextBox();
	private readonly TextBox description = new TextBox { Multiline = true, ScrollBars = ScrollBars.Vertical };
	public BankTransaction Value { get; private set; }

	public BankTransactionDialog(BankTransaction? existing = null, string initialType = "Deposit")
	{
		Value = existing ?? new BankTransaction { TransactionType = initialType };
		Text = existing == null ? "Record Bank " + initialType : "Edit Bank Transaction";
		ClientSize = new Size(620, 480); StartPosition = FormStartPosition.CenterParent;
		FormBorderStyle = FormBorderStyle.FixedDialog; MaximizeBox = false; MinimizeBox = false;
		Font = new Font("Segoe UI", 11f); BackColor = Color.FromArgb(248,249,252);
		transactionType.Items.AddRange(new object[] { "Deposit", "Withdrawal" });
		transactionDate.Value = Value.TransactionDate; transactionType.SelectedItem = Value.TransactionType;
		amount.Value = Math.Min(amount.Maximum, Math.Max(0, Value.Amount)); reference.Text = Value.ReferenceNumber; description.Text = Value.Description;

		TableLayoutPanel p = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(28), ColumnCount = 2, RowCount = 6 };
		p.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 170)); p.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
		Add(p, "Transaction date", transactionDate, 0); Add(p, "Transaction type", transactionType, 1); Add(p, "Amount", amount, 2);
		Add(p, "Reference / deposit slip", reference, 3); Add(p, "Description", description, 4);
		FlowLayoutPanel actions = new FlowLayoutPanel { Dock = DockStyle.Fill, FlowDirection = FlowDirection.RightToLeft };
		Button save = new Button { Text = "Save Transaction", Width = 155, Height = 42, BackColor = Color.FromArgb(0,47,95), ForeColor = Color.White, FlatStyle = FlatStyle.Flat };
		Button cancel = new Button { Text = "Cancel", Width = 110, Height = 42, DialogResult = DialogResult.Cancel };
		actions.Controls.Add(save); actions.Controls.Add(cancel); p.Controls.Add(actions, 1, 5); Controls.Add(p); AcceptButton = save; CancelButton = cancel;
		save.Click += delegate
		{
			if (amount.Value <= 0) { MessageBox.Show("Enter an amount greater than zero."); return; }
			if (string.IsNullOrWhiteSpace(description.Text)) { MessageBox.Show("Enter a transaction description."); return; }
			Value.TransactionDate = transactionDate.Value.Date; Value.TransactionType = transactionType.Text; Value.Amount = amount.Value;
			Value.ReferenceNumber = reference.Text.Trim(); Value.Description = description.Text.Trim(); DialogResult = DialogResult.OK;
		};
	}

	private static void Add(TableLayoutPanel p, string label, Control field, int row)
	{ p.Controls.Add(new Label { Text = label, AutoSize = true, Anchor = AnchorStyles.Left }, 0, row); field.Dock = DockStyle.Fill; p.Controls.Add(field, 1, row); }
}
