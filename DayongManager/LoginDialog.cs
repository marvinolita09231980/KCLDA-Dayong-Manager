using System;
using System.Drawing;
using System.IO;
using System.Security.Cryptography;
using System.Text;
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

	private readonly CheckBox rememberPassword = new CheckBox
	{
		Text = "Remember password",
		AutoSize = true,
		Margin = new Padding(0, 3, 22, 0)
	};

	public string AuthenticatedUsername { get; private set; } = "";

	public LoginDialog(DatabaseService database)
	{
		db = database;
		Text = "KCLDA Dayong Manager — Sign In";
		base.ClientSize = new Size(500, 410);
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
			Location = new Point(52, 125),
			Size = new Size(396, 260),
			ColumnCount = 1,
			RowCount = 7
		};
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 28f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 36f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 38f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 36f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 34f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 30f));
		tableLayoutPanel.RowStyles.Add(new RowStyle(SizeType.Absolute, 48f));
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
		tableLayoutPanel.Controls.Add(rememberPassword, 0, 4);
		tableLayoutPanel.Controls.Add(error, 0, 5);
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
		tableLayoutPanel.Controls.Add(button, 0, 6);
		base.Controls.Add(tableLayoutPanel);
		base.Controls.Add(panel);
		base.AcceptButton = button;
		if (RememberedLoginStore.TryLoad(out string savedUsername, out string savedPassword))
		{
			username.Text = savedUsername;
			password.Text = savedPassword;
			rememberPassword.Checked = true;
		}
		base.Shown += delegate
		{
			password.Focus();
		};
		button.Click += delegate
		{
			if (db.Authenticate(username.Text, password.Text))
			{
				AuthenticatedUsername = username.Text.Trim();
				if (rememberPassword.Checked)
				{
					RememberedLoginStore.Save(AuthenticatedUsername, password.Text);
				}
				else
				{
					RememberedLoginStore.Clear();
				}
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

internal static class RememberedLoginStore
{
	private static readonly byte[] Entropy = Encoding.UTF8.GetBytes("KCLDA.DayongManager.RememberedLogin.v1");

	private static string FilePath => Path.Combine(
		Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
		"KCLDA", "DayongManager", "remembered-login.dat");

	public static void Save(string username, string password)
	{
		string directory = Path.GetDirectoryName(FilePath)!;
		Directory.CreateDirectory(directory);
		byte[] plainText = Encoding.UTF8.GetBytes(username + "\n" + password);
		byte[] protectedData = ProtectedData.Protect(plainText, Entropy, DataProtectionScope.CurrentUser);
		File.WriteAllBytes(FilePath, protectedData);
		CryptographicOperations.ZeroMemory(plainText);
	}

	public static bool TryLoad(out string username, out string password)
	{
		username = "";
		password = "";
		try
		{
			if (!File.Exists(FilePath)) return false;
			byte[] plainText = ProtectedData.Unprotect(File.ReadAllBytes(FilePath), Entropy, DataProtectionScope.CurrentUser);
			string[] parts = Encoding.UTF8.GetString(plainText).Split('\n', 2);
			CryptographicOperations.ZeroMemory(plainText);
			if (parts.Length != 2 || string.IsNullOrWhiteSpace(parts[0])) return false;
			username = parts[0];
			password = parts[1];
			return true;
		}
		catch (CryptographicException)
		{
			Clear();
			return false;
		}
		catch (IOException)
		{
			return false;
		}
	}

	public static void Clear()
	{
		try
		{
			if (File.Exists(FilePath)) File.Delete(FilePath);
		}
		catch (IOException)
		{
			// A locked preferences file should not prevent sign-in.
		}
	}
}
