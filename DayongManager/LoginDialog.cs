using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class LoginDialog : Form
{
	private readonly DatabaseService db;

	private readonly TextBox username = new TextBox
	{
		Text = "admin"
	};

	private readonly TextBox password = new TextBox
	{
		UseSystemPasswordChar = true
	};

	private readonly Label error = new Label
	{
		AutoSize = true,
		ForeColor = Color.FromArgb(180, 25, 40)
	};

	public string AuthenticatedUsername { get; private set; } = "";

	public LoginDialog(DatabaseService database)
	{
		db = database;
		Text = "KCLDA Dayong Manager — Sign In";
		base.ClientSize = new Size(500, 390);
		base.StartPosition = FormStartPosition.CenterScreen;
		base.FormBorderStyle = FormBorderStyle.FixedDialog;
		base.MaximizeBox = false;
		base.MinimizeBox = false;
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(245, 247, 250);
		Panel panel = new Panel
		{
			Dock = DockStyle.Top,
			Height = 105,
			BackColor = Color.FromArgb(0, 47, 95)
		};
		panel.Controls.Add(new Label
		{
			Text = "KCLDA",
			ForeColor = Color.FromArgb(245, 190, 45),
			Font = new Font("Segoe UI Semibold", 24f),
			AutoSize = true,
			Location = new Point(28, 17)
		});
		panel.Controls.Add(new Label
		{
			Text = "Dayong Membership & Collection Manager",
			ForeColor = Color.White,
			AutoSize = true,
			Location = new Point(31, 65)
		});
		panel.Controls.Add(new Panel
		{
			Dock = DockStyle.Bottom,
			Height = 5,
			BackColor = Color.FromArgb(190, 30, 45)
		});
		TableLayoutPanel tableLayoutPanel = new TableLayoutPanel
		{
			Location = new Point(52, 135),
			Size = new Size(396, 218),
			ColumnCount = 1,
			RowCount = 7
		};
		tableLayoutPanel.Controls.Add(new Label
		{
			Text = "Username",
			AutoSize = true
		}, 0, 0);
		username.Dock = DockStyle.Fill;
		tableLayoutPanel.Controls.Add(username, 0, 1);
		tableLayoutPanel.Controls.Add(new Label
		{
			Text = "Password",
			AutoSize = true,
			Margin = new Padding(0, 12, 0, 0)
		}, 0, 2);
		password.Dock = DockStyle.Fill;
		tableLayoutPanel.Controls.Add(password, 0, 3);
		tableLayoutPanel.Controls.Add(error, 0, 4);
		Button button = new Button
		{
			Text = "Sign In",
			Dock = DockStyle.Fill,
			Height = 44,
			BackColor = Color.FromArgb(0, 47, 95),
			ForeColor = Color.White,
			FlatStyle = FlatStyle.Flat,
			Font = new Font("Segoe UI Semibold", 11f),
			Cursor = Cursors.Hand
		};
		button.FlatAppearance.BorderColor = Color.FromArgb(245, 190, 45);
		tableLayoutPanel.Controls.Add(button, 0, 5);
		base.Controls.Add(tableLayoutPanel);
		base.Controls.Add(panel);
		base.AcceptButton = button;
		base.Shown += delegate
		{
			password.Focus();
		};
		button.Click += delegate
		{
			if (db.Authenticate(username.Text, password.Text))
			{
				AuthenticatedUsername = username.Text.Trim();
				base.DialogResult = DialogResult.OK;
			}
			else
			{
				error.Text = "Incorrect username or password.";
				password.SelectAll();
				password.Focus();
			}
		};
	}
}
