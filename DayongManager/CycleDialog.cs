using System;
using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class CycleDialog : Form
{
	private readonly TextBox name = new TextBox();

	private readonly ComboBox type = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList
	};

	private readonly NumericUpDown amount = new NumericUpDown
	{
		Maximum = 100000m,
		DecimalPlaces = 2,
		ThousandsSeparator = true
	};

	private readonly DateTimePicker due = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly DateTimePicker start = new DateTimePicker
	{
		Format = DateTimePickerFormat.Short,
		ShowCheckBox = true
	};

	private readonly CheckBox active = new CheckBox
	{
		Text = "Active cycle",
		Checked = true
	};

	public CollectionCycle Value { get; private set; }

	public CycleDialog(CollectionCycle? x = null)
	{
		Value = x ?? new CollectionCycle();
		Text = ((x == null) ? "New Collection Cycle" : "Edit Collection Cycle");
		base.Width = 570;
		base.Height = 450;
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(248, 249, 252);
		base.StartPosition = FormStartPosition.CenterParent;
		type.Items.AddRange(new object[3] { "Dayong", "Annual Dues", "Registration Fee" });
		type.SelectedIndex = 0;
		type.SelectedIndexChanged += delegate
		{
			bool fixedFee = type.Text is "Annual Dues" or "Registration Fee";
			if (fixedFee) amount.Value = 100m;
			amount.Enabled = !fixedFee;
		};
		TableLayoutPanel tableLayoutPanel = new TableLayoutPanel
		{
			Dock = DockStyle.Fill,
			Padding = new Padding(26),
			ColumnCount = 2,
			RowCount = 7
		};
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 165f));
		tableLayoutPanel.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
		Add(tableLayoutPanel, "Cycle name", name, 0);
		Add(tableLayoutPanel, "Type", type, 1);
		Add(tableLayoutPanel, "Expected amount", amount, 2);
		Add(tableLayoutPanel, "Start date", start, 3);
		Add(tableLayoutPanel, "Due date", due, 4);
		tableLayoutPanel.Controls.Add(active, 1, 5);
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
			BackColor = Color.FromArgb(0, 47, 95),
			ForeColor = Color.White,
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f)
		};
		Button value = new Button
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
		flowLayoutPanel.Controls.Add(value);
		tableLayoutPanel.Controls.Add(flowLayoutPanel, 1, 6);
		base.Controls.Add(tableLayoutPanel);
		if (x != null)
		{
			name.Text = x.Name;
			type.SelectedItem = x.Type;
			amount.Value = x.ExpectedAmount;
			start.Checked = x.StartDate.HasValue;
			if (x.StartDate.HasValue)
			{
				start.Value = x.StartDate.Value;
			}
			due.Checked = x.DueDate.HasValue;
			if (x.DueDate.HasValue)
			{
				due.Value = x.DueDate.Value;
			}
			active.Checked = x.Active;
		}
		button.Click += delegate
		{
			if (string.IsNullOrWhiteSpace(name.Text) || amount.Value <= 0m)
			{
				MessageBox.Show("Enter a cycle name and expected amount.");
			}
			else
			{
				Value.Name = name.Text;
				Value.Type = type.Text;
				Value.ExpectedAmount = (type.Text is "Annual Dues" or "Registration Fee") ? 100m : amount.Value;
				Value.StartDate = (start.Checked ? new DateTime?(start.Value.Date) : ((DateTime?)null));
				Value.DueDate = (due.Checked ? new DateTime?(due.Value.Date) : ((DateTime?)null));
				Value.Active = active.Checked;
				base.DialogResult = DialogResult.OK;
			}
		};
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
