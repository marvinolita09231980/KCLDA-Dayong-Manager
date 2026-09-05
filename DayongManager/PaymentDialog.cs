using System;
using System.Collections.Generic;
using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class PaymentDialog : Form
{
	private readonly DataGridView duesGrid = new DataGridView();
	private readonly TextBox receipt = new TextBox();
	private readonly DateTimePicker date = new DateTimePicker { Format = DateTimePickerFormat.Short };
	private readonly Label total = new Label { AutoSize = true, Font = new Font("Segoe UI Semibold", 11f) };

	public string ReceiptNumber => receipt.Text.Trim();
	public DateTime DatePaid => date.Value.Date;
	public List<(long CycleId, decimal NewTotal)> Allocations { get; } = new List<(long, decimal)>();

	public PaymentDialog(string memberName, List<PaymentDue> dues)
	{
		Text = "Record Payment — " + memberName;
		Width = 920; Height = 580; MinimumSize = new Size(780, 480);
		Font = new Font("Segoe UI", 10.5f); BackColor = Color.FromArgb(248, 249, 252);
		StartPosition = FormStartPosition.CenterParent;
		TableLayoutPanel layout = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(22), RowCount = 5, ColumnCount = 1 };
		layout.RowStyles.Add(new RowStyle(SizeType.AutoSize)); layout.RowStyles.Add(new RowStyle(SizeType.Percent, 100f));
		layout.RowStyles.Add(new RowStyle(SizeType.AutoSize)); layout.RowStyles.Add(new RowStyle(SizeType.AutoSize)); layout.RowStyles.Add(new RowStyle(SizeType.AutoSize));
		layout.Controls.Add(new Label { Text = "Complete collectable account for " + memberName + "\nIncludes every active annual due and Dayong cycle, plus the member's one-time registration fee.", AutoSize = true, Font = new Font("Segoe UI Semibold", 11f), Margin = new Padding(0, 0, 0, 12) }, 0, 0);
		ConfigureGrid(dues); layout.Controls.Add(duesGrid, 0, 1); layout.Controls.Add(total, 0, 2);
		TableLayoutPanel details = new TableLayoutPanel { Dock = DockStyle.Top, AutoSize = true, ColumnCount = 4, Margin = new Padding(0, 14, 0, 12) };
		details.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize)); details.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 55f)); details.ColumnStyles.Add(new ColumnStyle(SizeType.AutoSize)); details.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 45f));
		details.Controls.Add(new Label { Text = "Receipt number *", AutoSize = true, Anchor = AnchorStyles.Left, Margin = new Padding(0, 7, 10, 0) }, 0, 0);
		receipt.Dock = DockStyle.Fill; details.Controls.Add(receipt, 1, 0);
		details.Controls.Add(new Label { Text = "Date paid", AutoSize = true, Anchor = AnchorStyles.Left, Margin = new Padding(20, 7, 10, 0) }, 2, 0);
		date.Dock = DockStyle.Fill; details.Controls.Add(date, 3, 0); layout.Controls.Add(details, 0, 3);
		FlowLayoutPanel actions = new FlowLayoutPanel { Dock = DockStyle.Fill, AutoSize = true, FlowDirection = FlowDirection.RightToLeft };
		Button save = MakeButton("Save Payment", Color.FromArgb(0, 47, 95), Color.White, DialogResult.OK);
		Button cancel = MakeButton("Cancel", Color.White, Color.FromArgb(0, 47, 95), DialogResult.Cancel);
		actions.Controls.Add(save); actions.Controls.Add(cancel); layout.Controls.Add(actions, 0, 4); Controls.Add(layout);
		AcceptButton = save; CancelButton = cancel; save.Click += ValidateAndCollect; RefreshTotal();
	}

	private void ConfigureGrid(List<PaymentDue> dues)
	{
		duesGrid.Dock = DockStyle.Fill; duesGrid.AllowUserToAddRows = false; duesGrid.AllowUserToDeleteRows = false;
		duesGrid.RowHeadersVisible = false; duesGrid.AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill; duesGrid.BackgroundColor = Color.White;
		duesGrid.Columns.Add(new DataGridViewCheckBoxColumn { Name = "Pay", HeaderText = "Pay", FillWeight = 35f });
		duesGrid.Columns.Add(new DataGridViewTextBoxColumn { Name = "Type", HeaderText = "Due Type", ReadOnly = true, FillWeight = 80f });
		duesGrid.Columns.Add(new DataGridViewTextBoxColumn { Name = "Cycle", HeaderText = "Cycle", ReadOnly = true, FillWeight = 150f });
		duesGrid.Columns.Add(new DataGridViewTextBoxColumn { Name = "Status", HeaderText = "Status", ReadOnly = true, FillWeight = 65f });
		foreach (string name in new[] { "Required", "PreviouslyPaid", "Balance", "PayNow" })
			duesGrid.Columns.Add(new DataGridViewTextBoxColumn { Name = name, HeaderText = name == "PreviouslyPaid" ? "Previously Paid" : name == "PayNow" ? "Pay Now" : name, ReadOnly = name != "PayNow", FillWeight = 75f });
		duesGrid.Columns.Add(new DataGridViewTextBoxColumn { Name = "CycleId", Visible = false });
		foreach (PaymentDue due in dues)
		{
			bool selected = due.IsSelectedCycle && due.Balance > 0m;
			string status = due.PreviouslyPaid >= due.Required && due.Required > 0m ? "Paid" : due.PreviouslyPaid > 0m ? "Partial" : "Unpaid";
			int row = duesGrid.Rows.Add(selected, due.Type, due.CycleName, status, due.Required, due.PreviouslyPaid, due.Balance, selected ? due.Balance : 0m, due.CycleId);
			if (due.Balance <= 0m) duesGrid.Rows[row].ReadOnly = true;
		}
		foreach (string name in new[] { "Required", "PreviouslyPaid", "Balance", "PayNow" }) duesGrid.Columns[name].DefaultCellStyle.Format = "₱#,##0.00";
		duesGrid.CurrentCellDirtyStateChanged += delegate { if (duesGrid.IsCurrentCellDirty) duesGrid.CommitEdit(DataGridViewDataErrorContexts.Commit); };
		duesGrid.CellValueChanged += delegate(object? sender, DataGridViewCellEventArgs e)
		{
			if (e.RowIndex >= 0 && e.ColumnIndex == duesGrid.Columns["Pay"].Index && Convert.ToBoolean(duesGrid.Rows[e.RowIndex].Cells["Pay"].Value))
			{
				DataGridViewCell payNow = duesGrid.Rows[e.RowIndex].Cells["PayNow"];
				if (!decimal.TryParse(Convert.ToString(payNow.Value), out decimal current) || current <= 0m) payNow.Value = duesGrid.Rows[e.RowIndex].Cells["Balance"].Value;
			}
			RefreshTotal();
		};
		duesGrid.DataError += delegate(object? sender, DataGridViewDataErrorEventArgs e) { e.ThrowException = false; };
		duesGrid.CellFormatting += delegate(object? sender, DataGridViewCellFormattingEventArgs e)
		{
			if (e.RowIndex < 0 || duesGrid.Columns[e.ColumnIndex].Name != "Status") return;
			string status = Convert.ToString(e.Value) ?? "";
			e.CellStyle.ForeColor = status == "Paid" ? Color.FromArgb(25, 135, 84) : status == "Partial" ? Color.FromArgb(180, 110, 0) : Color.FromArgb(190, 30, 45);
			e.CellStyle.Font = new Font("Segoe UI Semibold", 10f);
		};
	}

	private void RefreshTotal()
	{
		decimal value = 0m;
		foreach (DataGridViewRow row in duesGrid.Rows)
			if (Convert.ToBoolean(row.Cells["Pay"].Value) && decimal.TryParse(Convert.ToString(row.Cells["PayNow"].Value), out decimal amount)) value += amount;
		total.Text = $"TOTAL PAYMENT: ₱{value:N2}";
	}

	private void ValidateAndCollect(object? sender, EventArgs e)
	{
		Allocations.Clear();
		if (string.IsNullOrWhiteSpace(ReceiptNumber)) { MessageBox.Show("Enter the official receipt number."); DialogResult = DialogResult.None; receipt.Focus(); return; }
		foreach (DataGridViewRow row in duesGrid.Rows)
		{
			if (!Convert.ToBoolean(row.Cells["Pay"].Value)) continue;
			if (!decimal.TryParse(Convert.ToString(row.Cells["PayNow"].Value), out decimal payNow) || payNow <= 0m) { MessageBox.Show("Enter an amount greater than zero for every selected due."); DialogResult = DialogResult.None; return; }
			decimal balance = Convert.ToDecimal(row.Cells["Balance"].Value);
			if (payNow > balance) { MessageBox.Show($"Payment for '{row.Cells["Cycle"].Value}' cannot exceed its ₱{balance:N2} balance."); DialogResult = DialogResult.None; return; }
			Allocations.Add((Convert.ToInt64(row.Cells["CycleId"].Value), Convert.ToDecimal(row.Cells["PreviouslyPaid"].Value) + payNow));
		}
		if (Allocations.Count == 0) { MessageBox.Show("Select at least one due to pay."); DialogResult = DialogResult.None; }
	}

	private static Button MakeButton(string text, Color back, Color fore, DialogResult result) => new Button { Text = text, DialogResult = result, Width = 130, Height = 40, BackColor = back, ForeColor = fore, FlatStyle = FlatStyle.Flat, Font = new Font("Segoe UI Semibold", 10.5f) };
}
