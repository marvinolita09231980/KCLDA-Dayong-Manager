using System;
using System.Collections.Generic;
using System.Drawing;
using System.Linq;
using System.Windows.Forms;

namespace DayongManager;

public sealed class UserManagementForm : Form
{
	private readonly DatabaseService db;
	private readonly string currentUsername;
	private readonly DataGridView grid = new DataGridView
	{
		Dock = DockStyle.Fill, ReadOnly = true, AllowUserToAddRows = false, AllowUserToDeleteRows = false,
		AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill, SelectionMode = DataGridViewSelectionMode.FullRowSelect,
		MultiSelect = false, RowHeadersVisible = false, BackgroundColor = Color.White, BorderStyle = BorderStyle.None,
		EnableHeadersVisualStyles = false, ColumnHeadersHeight = 40
	};
	private List<UserAccess> users = new List<UserAccess>();

	public UserManagementForm(DatabaseService database, string signedInUser)
	{
		db = database; currentUsername = signedInUser;
		Text = "User Accounts & Permissions"; Width = 980; Height = 650; StartPosition = FormStartPosition.CenterParent;
		Font = new Font("Segoe UI", 11f); BackColor = Color.FromArgb(245, 247, 250);
		grid.ColumnHeadersDefaultCellStyle = new DataGridViewCellStyle { BackColor = Color.FromArgb(0,47,95), ForeColor = Color.White, Font = new Font("Segoe UI Semibold", 11f) };
		FlowLayoutPanel bar = new FlowLayoutPanel { Dock = DockStyle.Top, Height = 68, Padding = new Padding(12), BackColor = Color.White };
		bar.Controls.Add(ActionButton("Create User", AddUser, Color.FromArgb(25,135,84), 140));
		bar.Controls.Add(ActionButton("Edit Permissions", EditUser, Color.FromArgb(13,110,253), 165));
		bar.Controls.Add(ActionButton("Delete User", DeleteUser, Color.FromArgb(220,53,69), 130));
		Controls.Add(grid); Controls.Add(bar); grid.DoubleClick += EditUser; Load += delegate { RefreshUsers(); };
	}

	private static Button ActionButton(string text, EventHandler click, Color color, int width)
	{
		Button b = new Button { Text = text, Width = width, Height = 40, Margin = new Padding(6,2,6,0), BackColor = color, ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Cursor = Cursors.Hand };
		b.FlatAppearance.BorderSize = 0; b.Click += click; return b;
	}

	private void RefreshUsers()
	{
		users = db.GetUsers();
		grid.DataSource = users.Select((u, i) => new { No = i + 1, u.Id, u.Username, DisplayName = u.DisplayName, AccountType = u.IsAdmin ? "Administrator" : "Restricted user", Status = u.Active ? "Active" : "Disabled" }).ToList();
		if (grid.Columns.Contains("Id")) grid.Columns["Id"].Visible = false;
		if (grid.Columns.Contains("No")) { grid.Columns["No"].FillWeight = 25; grid.Columns["No"].MinimumWidth = 50; }
	}

	private UserAccess? SelectedUser()
	{
		if (grid.CurrentRow == null) return null;
		long id = Convert.ToInt64(grid.CurrentRow.Cells["Id"].Value);
		return users.FirstOrDefault(x => x.Id == id);
	}

	private void AddUser(object? sender, EventArgs e)
	{
		using UserEditorDialog dialog = new UserEditorDialog();
		if (dialog.ShowDialog(this) == DialogResult.OK) Save(dialog.Value, dialog.NewPassword);
	}

	private void EditUser(object? sender, EventArgs e)
	{
		UserAccess? selected = SelectedUser(); if (selected == null) return;
		using UserEditorDialog dialog = new UserEditorDialog(selected);
		if (dialog.ShowDialog(this) == DialogResult.OK) Save(dialog.Value, dialog.NewPassword);
	}

	private void Save(UserAccess user, string password)
	{
		try { db.SaveUser(user, password); RefreshUsers(); MessageBox.Show("User permissions saved.", "Saved", MessageBoxButtons.OK, MessageBoxIcon.Information); }
		catch (Exception ex) { MessageBox.Show("Could not save the user. The username may already exist.\n\n" + ex.Message, "Save User", MessageBoxButtons.OK, MessageBoxIcon.Error); }
	}

	private void DeleteUser(object? sender, EventArgs e)
	{
		UserAccess? selected = SelectedUser(); if (selected == null) return;
		if (selected.Username.Equals("admin", StringComparison.OrdinalIgnoreCase) || selected.Username.Equals(currentUsername, StringComparison.OrdinalIgnoreCase))
		{ MessageBox.Show("The built-in administrator or currently signed-in user cannot be deleted."); return; }
		if (MessageBox.Show("Delete user '" + selected.Username + "'?", "Confirm Delete", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) == DialogResult.Yes)
		{ db.DeleteUser(selected.Id); RefreshUsers(); }
	}
}

public sealed class UserEditorDialog : Form
{
	private readonly TextBox username = new TextBox();
	private readonly TextBox displayName = new TextBox();
	private readonly TextBox password = new TextBox { UseSystemPasswordChar = true };
	private readonly CheckBox active = new CheckBox { Text = "Account is active", Checked = true };
	private readonly CheckBox administrator = new CheckBox { Text = "Administrator — allow all tabs and actions" };
	private readonly CheckedListBox permissions = new CheckedListBox { CheckOnClick = true, Height = 360 };
	public UserAccess Value { get; private set; }
	public string NewPassword => password.Text;

	public UserEditorDialog(UserAccess? existing = null)
	{
		Value = existing == null ? new UserAccess() : new UserAccess { Id = existing.Id, Username = existing.Username, DisplayName = existing.DisplayName, Active = existing.Active, IsAdmin = existing.IsAdmin, PermissionsText = existing.PermissionsText };
		Text = existing == null ? "Create User" : "Edit User & Permissions"; Width = 720; Height = 790; StartPosition = FormStartPosition.CenterParent;
		Font = new Font("Segoe UI", 11f); BackColor = Color.FromArgb(248,249,252); FormBorderStyle = FormBorderStyle.FixedDialog; MaximizeBox = false; MinimizeBox = false;
		foreach ((string key, string label) in AppPermissions.All) permissions.Items.Add(label, Value.Permissions.Contains(key));
		username.Text = Value.Username; displayName.Text = Value.DisplayName; active.Checked = Value.Active; administrator.Checked = Value.IsAdmin;
		bool builtInAdmin = Value.Username.Equals("admin", StringComparison.OrdinalIgnoreCase);
		if (builtInAdmin) { username.ReadOnly = true; active.Enabled = false; administrator.Enabled = false; }

		TableLayoutPanel p = new TableLayoutPanel { Dock = DockStyle.Fill, Padding = new Padding(25), ColumnCount = 2, RowCount = 8, AutoScroll = true };
		p.ColumnStyles.Add(new ColumnStyle(SizeType.Absolute, 190)); p.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100));
		Add(p, "Username", username, 0); Add(p, "Display name", displayName, 1); Add(p, existing == null ? "Password" : "New password (optional)", password, 2);
		p.Controls.Add(active, 1, 3); p.Controls.Add(administrator, 1, 4);
		p.Controls.Add(new Label { Text = "Allowed tabs and actions", AutoSize = true, Anchor = AnchorStyles.Left }, 0, 5);
		permissions.Dock = DockStyle.Fill; p.Controls.Add(permissions, 1, 5);
		FlowLayoutPanel selectBar = new FlowLayoutPanel { Dock = DockStyle.Fill };
		Button all = new Button { Text = "Select All", Width = 105 }; Button none = new Button { Text = "Clear All", Width = 105 };
		all.Click += delegate { for (int i=0;i<permissions.Items.Count;i++) permissions.SetItemChecked(i,true); };
		none.Click += delegate { for (int i=0;i<permissions.Items.Count;i++) permissions.SetItemChecked(i,false); };
		selectBar.Controls.Add(all); selectBar.Controls.Add(none); p.Controls.Add(selectBar, 1, 6);
		FlowLayoutPanel actions = new FlowLayoutPanel { Dock = DockStyle.Fill, FlowDirection = FlowDirection.RightToLeft };
		Button save = new Button { Text = "Save User", Width = 120, Height = 42, BackColor = Color.FromArgb(0,47,95), ForeColor = Color.White, FlatStyle = FlatStyle.Flat };
		Button cancel = new Button { Text = "Cancel", Width = 110, Height = 42, DialogResult = DialogResult.Cancel };
		actions.Controls.Add(save); actions.Controls.Add(cancel); p.Controls.Add(actions, 1, 7); Controls.Add(p); AcceptButton = save; CancelButton = cancel;
		administrator.CheckedChanged += delegate { permissions.Enabled = !administrator.Checked; };
		permissions.Enabled = !administrator.Checked;
		save.Click += delegate
		{
			if (string.IsNullOrWhiteSpace(username.Text)) { MessageBox.Show("Username is required."); return; }
			if (Value.Id == 0 && password.Text.Length < 8) { MessageBox.Show("A new user password must contain at least 8 characters."); return; }
			if (password.Text.Length > 0 && password.Text.Length < 8) { MessageBox.Show("The password must contain at least 8 characters."); return; }
			Value.Username = username.Text.Trim(); Value.DisplayName = displayName.Text.Trim(); Value.Active = active.Checked; Value.IsAdmin = administrator.Checked;
			HashSet<string> selected = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
			for (int i=0;i<permissions.Items.Count;i++) if (permissions.GetItemChecked(i)) selected.Add(AppPermissions.All[i].Key);
			EnsureView(selected, "Members", "ViewMembers"); EnsureView(selected, "Collections", "ViewCollections"); EnsureView(selected, "Compliance", "ViewCompliance"); EnsureView(selected, "Ledger", "ViewLedger"); EnsureView(selected, "Disbursements", "ViewDisbursements"); EnsureView(selected, "Cycles", "ViewCycles"); EnsureView(selected, "Tools", "ViewTools");
			Value.PermissionsText = string.Join("|", selected); DialogResult = DialogResult.OK;
		};
	}

	private static void EnsureView(HashSet<string> values, string group, string view)
	{ if (values.Any(x => x != view && (group == "Members" ? x.EndsWith("Members") : group == "Collections" ? x.Contains("Payments") || x.Contains("Treasurer") : group == "Compliance" ? x.Contains("ReviewMember") : group == "Ledger" ? x.EndsWith("Ledger") : group == "Disbursements" ? x.EndsWith("Disbursements") : group == "Cycles" ? x.EndsWith("Cycles") : x is "ImportExcel" or "ExportExcel" or "BackupDatabase" or "OpenDataFolder" or "OpenBylaws" or "ManageUsers"))) values.Add(view); }

	private static void Add(TableLayoutPanel p, string label, Control field, int row)
	{ p.Controls.Add(new Label { Text = label, AutoSize = true, Anchor = AnchorStyles.Left }, 0, row); field.Dock = DockStyle.Fill; p.Controls.Add(field, 1, row); }
}
