using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class ChangePasswordDialog : Form
{
	public ChangePasswordDialog(DatabaseService db, string username)
	{
		ChangePasswordDialog changePasswordDialog = this;
		Text = "Change Password";
		base.ClientSize = new Size(490, 360);
		base.StartPosition = FormStartPosition.CenterParent;
		base.FormBorderStyle = FormBorderStyle.FixedDialog;
		base.MaximizeBox = false;
		base.MinimizeBox = false;
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(248, 249, 252);
		TextBox current = new TextBox
		{
			UseSystemPasswordChar = true
		};
		TextBox next = new TextBox
		{
			UseSystemPasswordChar = true
		};
		TextBox confirm = new TextBox
		{
			UseSystemPasswordChar = true
		};
		TableLayoutPanel tableLayoutPanel = new TableLayoutPanel
		{
			Dock = DockStyle.Fill,
			Padding = new Padding(30),
			ColumnCount = 1,
			RowCount = 8
		};
		tableLayoutPanel.Controls.Add(new Label
		{
			Text = "Signed in as " + username,
			Font = new Font("Segoe UI Semibold", 13f),
			AutoSize = true
		});
		Add(tableLayoutPanel, "Current password", current);
		Add(tableLayoutPanel, "New password", next);
		Add(tableLayoutPanel, "Confirm new password", confirm);
		Button button = new Button
		{
			Text = "Update Password",
			Height = 43,
			Dock = DockStyle.Fill,
			BackColor = Color.FromArgb(0, 47, 95),
			ForeColor = Color.White,
			FlatStyle = FlatStyle.Flat
		};
		tableLayoutPanel.Controls.Add(button);
		base.Controls.Add(tableLayoutPanel);
		base.AcceptButton = button;
		button.Click += delegate
		{
			if (next.Text.Length < 8)
			{
				MessageBox.Show("The new password must contain at least 8 characters.");
			}
			else if (next.Text != confirm.Text)
			{
				MessageBox.Show("The new passwords do not match.");
			}
			else if (!db.ChangePassword(username, current.Text, next.Text))
			{
				MessageBox.Show("The current password is incorrect.");
			}
			else
			{
				MessageBox.Show("Your password was changed successfully.", "Password Updated", MessageBoxButtons.OK, MessageBoxIcon.Asterisk);
				changePasswordDialog.DialogResult = DialogResult.OK;
			}
		};
	}

	private static void Add(TableLayoutPanel p, string label, TextBox box)
	{
		p.Controls.Add(new Label
		{
			Text = label,
			AutoSize = true,
			Margin = new Padding(0, 9, 0, 2)
		});
		box.Dock = DockStyle.Fill;
		p.Controls.Add(box);
	}
}
