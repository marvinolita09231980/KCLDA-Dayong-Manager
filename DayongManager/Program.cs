using System;
using System.IO;
using System.Windows.Forms;

namespace DayongManager;

internal static class Program
{
	[STAThread]
	private static void Main()
	{
		try
		{
			Application.EnableVisualStyles();
			Application.SetCompatibleTextRenderingDefault(defaultValue: false);
			Application.SetHighDpiMode(HighDpiMode.SystemAware);
			DatabaseService databaseService = new DatabaseService();
			databaseService.Initialize();
			while (true)
			{
				using LoginDialog loginDialog = new LoginDialog(databaseService);
				if (loginDialog.ShowDialog() != DialogResult.OK) break;

				using MainForm mainForm = new MainForm(databaseService, loginDialog.AuthenticatedUsername);
				Application.Run(mainForm);
				if (!mainForm.LogoutRequested) break;
			}
		}
		catch (Exception ex)
		{
			string text = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "KCLDA", "DayongManager");
			Directory.CreateDirectory(text);
			string text2 = Path.Combine(text, "startup-error.txt");
			File.WriteAllText(text2, $"{DateTime.Now:O}\r\n{ex}");
			MessageBox.Show("Dayong Manager could not start.\n\nA diagnostic file was saved to:\n" + text2 + "\n\n" + ex.Message, "Startup Error", MessageBoxButtons.OK, MessageBoxIcon.Hand);
		}
	}
}
