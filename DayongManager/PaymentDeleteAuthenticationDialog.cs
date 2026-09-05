using System.Drawing;
using System.Windows.Forms;

namespace DayongManager;

public sealed class PaymentDeleteAuthenticationDialog : Form
{
	private readonly DatabaseService db;
	private readonly string username;
	private readonly TextBox password = new TextBox { UseSystemPasswordChar = true, Dock = DockStyle.Fill };
	private readonly Label error = new Label { AutoSize = true, ForeColor = Color.FromArgb(190, 30, 45) };

	public PaymentDeleteAuthenticationDialog(DatabaseService database, string currentUsername)
	{
		db = database; username = currentUsername;
		Text = "Authenticate Payment Deletion"; ClientSize = new Size(460, 250);
		StartPosition = FormStartPosition.CenterParent; FormBorderStyle = FormBorderStyle.FixedDialog;
		MaximizeBox = false; MinimizeBox = false; Font = new Font("Segoe UI", 10.5f);
		TableLayoutPanel layout = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(25), ColumnCount = 1, RowCount = 6 };
		layout.Controls.Add(new Label { Text = "Deleting a payment requires authentication.", AutoSize = true, Font = new Font("Segoe UI Semibold", 11f) }, 0, 0);
		layout.Controls.Add(new Label { Text = "Signed in as: " + username, AutoSize = true, Margin = new Padding(0, 8, 0, 8) }, 0, 1);
		layout.Controls.Add(new Label { Text = "Enter your password", AutoSize = true }, 0, 2);
		layout.Controls.Add(password, 0, 3); layout.Controls.Add(error, 0, 4);
		Button authenticate = new Button { Text = "Authenticate", Dock = DockStyle.Right, Width = 130, Height = 38, BackColor = Color.FromArgb(0, 47, 95), ForeColor = Color.White, FlatStyle = FlatStyle.Flat };
		layout.Controls.Add(authenticate, 0, 5); Controls.Add(layout); AcceptButton = authenticate;
		authenticate.Click += delegate
		{
			if (db.Authenticate(username, password.Text)) DialogResult = DialogResult.OK;
			else { error.Text = "Incorrect password."; password.SelectAll(); password.Focus(); }
		};
		Shown += delegate { password.Focus(); };
	}
}
