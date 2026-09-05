using System;
using System.Collections;
using System.Collections.Generic;
using System.Data;
using System.Diagnostics;
using System.Drawing;
using System.Drawing.Printing;
using System.IO;
using System.Linq;
using System.Windows.Forms;

namespace DayongManager;

public sealed class MainForm : Form
{
	private static readonly Color KofcNavy = Color.FromArgb(0, 47, 95);

	private static readonly Color KofcRed = Color.FromArgb(190, 30, 45);

	private static readonly Color KofcGold = Color.FromArgb(245, 190, 45);

	private static readonly Color SuccessGreen = Color.FromArgb(25, 135, 84);

	private static readonly Color InfoBlue = Color.FromArgb(13, 110, 253);

	private static readonly Color DangerRed = Color.FromArgb(220, 53, 69);

	private readonly DatabaseService db;

	private readonly string currentUsername;

	private readonly UserAccess currentAccess;

	private readonly TabControl tabs = new TabControl
	{
		Dock = DockStyle.Fill
	};

	private readonly DataGridView memberGrid = Grid();

	private readonly DataGridView cycleGrid = Grid();

	private readonly DataGridView paymentGrid = Grid();

	private readonly DataGridView complianceGrid = Grid();

	private readonly DataGridView ledgerGrid = Grid();

	private readonly DataGridView disbursementGrid = Grid();

	private List<ComplianceRow> compliance = new List<ComplianceRow>();

	private readonly TextBox search = new TextBox
	{
		PlaceholderText = "Search member...",
		Width = 240
	};

	private readonly ComboBox memberCouncil = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 150
	};

	private readonly ComboBox memberCycle = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 240
	};

	private readonly ComboBox paymentCouncil = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 150
	};

	private readonly ComboBox paymentCycle = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 260
	};

	private readonly ComboBox paymentType = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 170
	};

	private readonly ComboBox dashboardCycle = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Dock = DockStyle.Fill
	};

	private readonly ComboBox complianceCouncil = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 170
	};

	private readonly TextBox paymentSearch = new TextBox
	{
		PlaceholderText = "Search name...",
		Width = 190
	};

	private readonly ComboBox paymentStatusFilter = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 120
	};

	private readonly TextBox complianceSearch = new TextBox
	{
		PlaceholderText = "Search name...",
		Width = 190
	};

	private readonly ComboBox complianceRecommendationFilter = new ComboBox
	{
		DropDownStyle = ComboBoxStyle.DropDownList,
		Width = 210
	};

	private readonly Label membersCard = Card();

	private readonly Label paidCard = Card();

	private readonly Label collectedCard = Card();

	private readonly Label balanceCard = Card();

	private readonly Label registrationCard = Card();

	private readonly Label annualDuesCard = Card();

	private readonly Label dayongCollectionsCard = Card();

	private readonly Label expensesCard = Card();

	private readonly Label availableFundsCard = Card();

	private List<Member> members = new List<Member>();

	private List<CollectionCycle> cycles = new List<CollectionCycle>();

	private List<PaymentRow> payments = new List<PaymentRow>();

	private List<PaymentRow> reportPayments = new List<PaymentRow>();

	private List<BankTransaction> bankTransactions = new List<BankTransaction>();

	private List<Disbursement> disbursements = new List<Disbursement>();

	private readonly Label ledgerBalance = new Label { AutoSize = true, Font = new Font("Segoe UI Semibold", 13f), ForeColor = Color.FromArgb(0,47,95), Margin = new Padding(22,8,10,0) };

	private readonly Label disbursementBalance = new Label { AutoSize = true, Font = new Font("Segoe UI Semibold", 12.5f), ForeColor = Color.FromArgb(0,47,95), Margin = new Padding(18,8,10,0) };

	private readonly PrintDocument printDocument = new PrintDocument();

	private readonly PrintDocument ledgerPrintDocument = new PrintDocument();

	private readonly PrintDocument disbursementPrintDocument = new PrintDocument();

	private int printRow;

	private int ledgerPrintRow;

	private decimal ledgerPrintBalance;

	private int disbursementPrintRow;

	private decimal disbursementPrintBalance;

	private LanSyncService? lanSyncService;

	public bool LogoutRequested { get; private set; }

	public MainForm(DatabaseService database, string username = "admin")
	{
		db = database;
		currentUsername = username;
		currentAccess = db.GetUserAccess(username) ?? throw new InvalidOperationException("The signed-in user account could not be loaded.");
		Text = "KCLDA Dayong Membership and Collection Manager";
		base.WindowState = FormWindowState.Maximized;
		MinimumSize = new Size(1200, 720);
		Font = new Font("Segoe UI", 11f);
		BackColor = Color.FromArgb(245, 247, 250);
		tabs.DrawMode = TabDrawMode.OwnerDrawFixed;
		tabs.SizeMode = TabSizeMode.Fixed;
		tabs.ItemSize = new Size(190, 46);
		tabs.DrawItem += DrawWebTab;
		paymentStatusFilter.Items.AddRange(new object[4] { "All", "Paid", "Partial", "Unpaid" });
		paymentStatusFilter.SelectedIndex = 0;
		complianceRecommendationFilter.Items.AddRange(new object[4] { "All Recommendations", "No action", "Review for Inactive status", "Subject for Board expulsion review" });
		complianceRecommendationFilter.SelectedIndex = 0;
		Panel panel = new Panel
		{
			Dock = DockStyle.Top,
			Height = 78,
			BackColor = KofcNavy
		};
		panel.Controls.Add(new Label
		{
			Text = "KCLDA  •  DAYONG MANAGER",
			ForeColor = KofcGold,
			Font = new Font("Segoe UI Semibold", 18f),
			AutoSize = true,
			Location = new Point(24, 20)
		});
		FlowLayoutPanel accountActions = new FlowLayoutPanel
		{
			Dock = DockStyle.Right,
			Width = 300,
			Padding = new Padding(0, 17, 18, 0),
			FlowDirection = FlowDirection.RightToLeft,
			WrapContents = false,
			BackColor = KofcNavy
		};
		Button logoutButton = HeaderButton("Logout", 90);
		Button changePasswordButton = HeaderButton("Change Password", 155);
		logoutButton.Click += delegate
		{
			LogoutRequested = true;
			Close();
		};
		changePasswordButton.Click += ChangePassword;
		accountActions.Controls.Add(logoutButton);
		accountActions.Controls.Add(changePasswordButton);
		panel.Controls.Add(accountActions);
		panel.Controls.Add(new Panel
		{
			Dock = DockStyle.Bottom,
			Height = 5,
			BackColor = KofcRed
		});
		base.Controls.Add(tabs);
		base.Controls.Add(panel);
		if (Can("ViewDashboard")) tabs.TabPages.Add(DashboardTab());
		if (Can("ViewMembers")) tabs.TabPages.Add(MembersTab());
		if (Can("ViewCollections")) tabs.TabPages.Add(CollectionsTab());
		if (Can("ViewCompliance")) tabs.TabPages.Add(ComplianceTab());
		if (Can("ViewLedger")) tabs.TabPages.Add(BankLedgerTab());
		if (Can("ViewDisbursements")) tabs.TabPages.Add(DisbursementLedgerTab());
		if (Can("ViewCycles")) tabs.TabPages.Add(CyclesTab());
		if (Can("ViewTools")) tabs.TabPages.Add(ToolsTab());
		if (tabs.TabPages.Count == 0) tabs.TabPages.Add(new TabPage("No Access") { Controls = { new Label { Text = "Your account has no assigned page access. Contact the administrator.", AutoSize = true, Location = new Point(30,30) } } });
		printDocument.PrintPage += PrintPage;
		ledgerPrintDocument.PrintPage += PrintLedgerPage;
		disbursementPrintDocument.PrintPage += PrintDisbursementPage;
		base.Load += delegate
		{
			TryInitialImport();
			RefreshAll();
		};
		base.Shown += async delegate { await AppUpdateService.CheckAsync(this, true); };
	}

	private bool Can(string permission) => currentAccess.Can(permission);

	private TabPage DashboardTab()
	{
		TabPage tabPage = new TabPage("Dashboard")
		{
			Padding = new Padding(20),
			AutoScroll = true
		};
		Label value = new Label
		{
			Text = "Collection Overview",
			Font = new Font("Segoe UI Semibold", 22f),
			Dock = DockStyle.Fill,
			TextAlign = ContentAlignment.MiddleLeft
		};
		TableLayoutPanel DashboardSection(string sectionTitle, params Label[] cards)
		{
			TableLayoutPanel cardLayout = new TableLayoutPanel
			{
				Dock = DockStyle.Fill,
				ColumnCount = cards.Length,
				RowCount = 1,
				Padding = new Padding(4),
				BackColor = Color.FromArgb(245, 247, 250)
			};
			for (int column = 0; column < cards.Length; column++)
			{
				cardLayout.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f / cards.Length));
				cards[column].Dock = DockStyle.Fill;
				cards[column].Margin = new Padding(6, 6, 6, 14);
				cardLayout.Controls.Add(cards[column], column, 0);
			}

			TableLayoutPanel section = new TableLayoutPanel
			{
				Dock = DockStyle.Fill,
				ColumnCount = 1,
				RowCount = 2,
				Margin = new Padding(0, 0, 0, 8),
				BackColor = Color.FromArgb(245, 247, 250)
			};
			section.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
			section.RowStyles.Add(new RowStyle(SizeType.Absolute, 28f));
			section.RowStyles.Add(new RowStyle(SizeType.Percent, 100f));
			section.Controls.Add(new Label
			{
				Text = sectionTitle,
				Dock = DockStyle.Fill,
				TextAlign = ContentAlignment.MiddleLeft,
				Font = new Font("Segoe UI Semibold", 12.5f),
				ForeColor = KofcNavy,
				Padding = new Padding(10, 0, 0, 0)
			}, 0, 0);
			section.Controls.Add(cardLayout, 0, 1);
			return section;
		}

		TableLayoutPanel cycleSection = DashboardSection("CYCLE", membersCard, paidCard, balanceCard);
		TableLayoutPanel collectionSection = DashboardSection("COLLECTION", collectedCard, registrationCard, annualDuesCard, dayongCollectionsCard);
		TableLayoutPanel disbursementSection = DashboardSection("DISBURSEMENT", expensesCard, availableFundsCard);
		Label value3 = new Label
		{
			Text = "Choose a cycle above to refresh the dashboard totals.",
			Dock = DockStyle.Fill,
			ForeColor = Color.DimGray,
			TextAlign = ContentAlignment.MiddleLeft,
			Padding = new Padding(8, 0, 0, 0)
		};
		TableLayoutPanel dashboardHeader = new TableLayoutPanel
		{
			Dock = DockStyle.Fill,
			ColumnCount = 1,
			RowCount = 2
		};
		dashboardHeader.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
		dashboardHeader.RowStyles.Add(new RowStyle(SizeType.Absolute, 58f));
		dashboardHeader.RowStyles.Add(new RowStyle(SizeType.Absolute, 45f));
		dashboardHeader.Controls.Add(value, 0, 0);
		FlowLayoutPanel cyclePicker = new FlowLayoutPanel
		{
			Dock = DockStyle.Fill,
			FlowDirection = FlowDirection.LeftToRight,
			WrapContents = false,
			Padding = new Padding(8, 4, 0, 0)
		};
		cyclePicker.Controls.Add(new Label
		{
			Text = "Cycle",
			AutoSize = true,
			Margin = new Padding(0, 7, 8, 0)
		});
		dashboardCycle.Width = 260;
		cyclePicker.Controls.Add(dashboardCycle);
		dashboardHeader.Controls.Add(cyclePicker, 0, 1);
		TableLayoutPanel dashboardLayout = new TableLayoutPanel
		{
			Dock = DockStyle.Top,
			Height = 685,
			ColumnCount = 1,
			RowCount = 5,
			BackColor = Color.FromArgb(245, 247, 250)
		};
		dashboardLayout.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 100f));
		dashboardLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 103f));
		dashboardLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 180f));
		dashboardLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 180f));
		dashboardLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 180f));
		dashboardLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 42f));
		dashboardLayout.Controls.Add(dashboardHeader, 0, 0);
		dashboardLayout.Controls.Add(cycleSection, 0, 1);
		dashboardLayout.Controls.Add(collectionSection, 0, 2);
		dashboardLayout.Controls.Add(disbursementSection, 0, 3);
		dashboardLayout.Controls.Add(value3, 0, 4);
		tabPage.Controls.Add(dashboardLayout);
		dashboardCycle.SelectedIndexChanged += delegate
		{
			if (dashboardCycle.SelectedItem is CollectionCycle selected &&
				(paymentCycle.SelectedItem as CollectionCycle)?.Id != selected.Id)
			{
				paymentType.SelectedValue = selected.Type;
				PopulatePaymentCycles(selected.Id);
				paymentCycle.SelectedValue = selected.Id;
			}
			else
			{
				RefreshDashboard();
			}
		};
		return tabPage;
	}

	private TabPage MembersTab()
	{
		TabPage tabPage = new TabPage("Members");
		FlowLayoutPanel flowLayoutPanel = Bar();
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Search",
				AutoSize = true,
				Margin = new Padding(5, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(search);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Council",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(memberCouncil);
		flowLayoutPanel.Controls.Add(new Label
		{
			Text = "Cycle",
			AutoSize = true,
			Margin = new Padding(15, 10, 2, 0)
		});
		flowLayoutPanel.Controls.Add(memberCycle);
		if (Can("AddMembers")) flowLayoutPanel.Controls.Add(Button("Add Member", AddMember));
		tabPage.Controls.Add(memberGrid);
		tabPage.Controls.Add(flowLayoutPanel);
		search.TextChanged += delegate
		{
			RefreshMembers();
		};
		memberCouncil.SelectedIndexChanged += delegate
		{
			RefreshMembers();
		};
		memberCycle.SelectedIndexChanged += delegate
		{
			RefreshMembers();
		};
		memberGrid.CellContentClick += MemberGridCellContentClick;
		return tabPage;
	}

	private TabPage CollectionsTab()
	{
		TabPage tabPage = new TabPage("Collections & Dues");
		FlowLayoutPanel flowLayoutPanel = Bar();
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Collection Type",
				AutoSize = true,
				Margin = new Padding(5, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(paymentType);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Cycle",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(paymentCycle);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Council",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(paymentCouncil);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Name",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(paymentSearch);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Status",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(paymentStatusFilter);
		if (Can("PrintTreasurerReport")) flowLayoutPanel.Controls.Add(Button("Treasurer's Report", PrintReport, 180));
		tabPage.Controls.Add(paymentGrid);
		tabPage.Controls.Add(flowLayoutPanel);
		paymentCycle.SelectedIndexChanged += delegate
		{
			if (paymentCycle.SelectedItem is CollectionCycle selected &&
				(dashboardCycle.SelectedItem as CollectionCycle)?.Id != selected.Id)
			{
				dashboardCycle.SelectedValue = selected.Id;
			}
			RefreshPayments();
		};
		paymentType.SelectedIndexChanged += delegate
		{
			PopulatePaymentCycles();
		};
		paymentCouncil.SelectedIndexChanged += delegate
		{
			RefreshPayments();
		};
		paymentSearch.TextChanged += delegate
		{
			RefreshPayments();
		};
		paymentStatusFilter.SelectedIndexChanged += delegate
		{
			RefreshPayments();
		};
		paymentGrid.CellContentClick += PaymentGridCellContentClick;
		return tabPage;
	}

	private TabPage CyclesTab()
	{
		TabPage tabPage = new TabPage("Collection Cycles");
		FlowLayoutPanel flowLayoutPanel = Bar();
		if (Can("AddCycles")) flowLayoutPanel.Controls.Add(Button("New Cycle", AddCycle));
		if (Can("EditCycles")) flowLayoutPanel.Controls.Add(Button("Edit", EditCycle));
		if (Can("DeleteCycles")) flowLayoutPanel.Controls.Add(Button("Delete", DeleteCycle));
		tabPage.Controls.Add(cycleGrid);
		tabPage.Controls.Add(flowLayoutPanel);
		if (Can("EditCycles")) cycleGrid.DoubleClick += EditCycle;
		return tabPage;
	}

	private TabPage BankLedgerTab()
	{
		TabPage tabPage = new TabPage("Bank Ledger");
		FlowLayoutPanel toolbar = Bar();
		ledgerBalance.Text = "BANK BALANCE  ₱0.00";
		toolbar.Controls.Add(ledgerBalance);
		if (Can("AddLedger"))
		{
			Button deposit = Button("Record Deposit", AddDeposit, 155);
			deposit.BackColor = SuccessGreen;
			Button withdrawal = Button("Record Withdrawal", AddWithdrawal, 175);
			withdrawal.BackColor = DangerRed;
			toolbar.Controls.Add(deposit);
			toolbar.Controls.Add(withdrawal);
		}
		if (Can("PrintLedger")) toolbar.Controls.Add(Button("Print Ledger", PrintLedger, 140));
		tabPage.Controls.Add(ledgerGrid);
		tabPage.Controls.Add(toolbar);
		ledgerGrid.CellContentClick += LedgerGridCellContentClick;
		return tabPage;
	}

	private TabPage DisbursementLedgerTab()
	{
		TabPage tabPage = new TabPage("Disbursement Ledger");
		FlowLayoutPanel toolbar = Bar();
		toolbar.Controls.Add(disbursementBalance);
		if (Can("AddDisbursements"))
		{
			Button record = Button("Record Expense", AddDisbursement, 155); record.BackColor = DangerRed; toolbar.Controls.Add(record);
		}
		if (Can("PrintDisbursements")) toolbar.Controls.Add(Button("Print Disbursements", PrintDisbursements, 185));
		tabPage.Controls.Add(disbursementGrid); tabPage.Controls.Add(toolbar);
		disbursementGrid.CellContentClick += DisbursementGridCellContentClick;
		return tabPage;
	}

	private TabPage ComplianceTab()
	{
		TabPage tabPage = new TabPage("Good Standing & Compliance");
		FlowLayoutPanel flowLayoutPanel = Bar();
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Council",
				AutoSize = true,
				Margin = new Padding(5, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(complianceCouncil);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Name",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(complianceSearch);
		flowLayoutPanel.Controls.Add(new Label
			{
				Text = "Recommendation",
				AutoSize = true,
				Margin = new Padding(15, 10, 2, 0)
			});
		flowLayoutPanel.Controls.Add(complianceRecommendationFilter);
		flowLayoutPanel.Controls.Add(Button("Refresh Review", delegate
			{
				RefreshCompliance();
			}, 140));
		if (Can("ReviewMemberStatus")) flowLayoutPanel.Controls.Add(Button("Review Member Status", ReviewComplianceMember, 180));
		tabPage.Controls.Add(complianceGrid);
		tabPage.Controls.Add(flowLayoutPanel);
		complianceCouncil.SelectedIndexChanged += delegate
		{
			RefreshCompliance();
		};
		complianceSearch.TextChanged += delegate
		{
			RefreshCompliance();
		};
		complianceRecommendationFilter.SelectedIndexChanged += delegate
		{
			RefreshCompliance();
		};
		complianceGrid.CellFormatting += ComplianceGridCellFormatting;
		if (Can("ReviewMemberStatus")) complianceGrid.DoubleClick += ReviewComplianceMember;
		return tabPage;
	}

	private TabPage ToolsTab()
	{
		TabPage tabPage = new TabPage("Import, Export & Backup");
		FlowLayoutPanel flowLayoutPanel = new FlowLayoutPanel
		{
			Dock = DockStyle.Top,
			Height = 120,
			Padding = new Padding(25),
			WrapContents = true
		};
		if (Can("ImportExcel")) flowLayoutPanel.Controls.Add(Button("Import Excel Workbook", ImportExcel, 190));
		if (Can("ExportExcel")) flowLayoutPanel.Controls.Add(Button("Export to Excel", ExportExcel, 160));
		if (Can("BackupDatabase")) flowLayoutPanel.Controls.Add(Button("Back Up Database", Backup, 170));
		if (Can("OpenDataFolder")) flowLayoutPanel.Controls.Add(Button("Open Data Folder", OpenFolder, 170));
		if (Can("OpenBylaws")) flowLayoutPanel.Controls.Add(Button("Open KCLDA By-Laws", OpenBylaws, 180));
		flowLayoutPanel.Controls.Add(Button("Change Password", ChangePassword, 170));
		flowLayoutPanel.Controls.Add(Button("Check for Updates", CheckForUpdates, 175));
		if (Can("ManageUsers")) flowLayoutPanel.Controls.Add(Button("Manage Users", ManageUsers, 160));
		flowLayoutPanel.Controls.Add(Button("Start LAN Sync", StartLanSync, 170));
		tabPage.Controls.Add(flowLayoutPanel);
		tabPage.Controls.Add(new Label
		{
			Text = "Tip: make a database backup after every major collection update.",
			AutoSize = true,
			Location = new Point(30, 150),
			ForeColor = Color.DimGray
		});
		return tabPage;
	}

	private async void StartLanSync(object? sender, EventArgs e)
	{
		try
		{
			lanSyncService ??= new LanSyncService(db);
			await lanSyncService.StartAsync();
			MessageBox.Show($"LAN Sync is ready. Keep this Windows app open.\n\nServer address: {lanSyncService.Address}\nSync code: {lanSyncService.Code}\n\nEnter these values on the Android Sync tab. Both devices must be on the same Wi-Fi network.", "LAN Sync", MessageBoxButtons.OK, MessageBoxIcon.Information);
		}
		catch (Exception ex)
		{
			MessageBox.Show("Could not start LAN Sync. Allow the app through Windows Firewall and try again.\n\n" + ex.Message, "LAN Sync Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
		}
	}

	protected override void OnFormClosed(FormClosedEventArgs e)
	{
		if (lanSyncService != null) lanSyncService.DisposeAsync().AsTask().GetAwaiter().GetResult();
		base.OnFormClosed(e);
	}

	private void ChangePassword(object? sender, EventArgs e)
	{
		using ChangePasswordDialog changePasswordDialog = new ChangePasswordDialog(db, currentUsername);
		if (changePasswordDialog.ShowDialog(this) == DialogResult.OK)
		{
			RememberedLoginStore.Clear();
		}
	}

	private async void CheckForUpdates(object? sender, EventArgs e)
	{
		await AppUpdateService.CheckAsync(this, false);
	}

	private void ManageUsers(object? sender, EventArgs e)
	{
		if (!Can("ManageUsers")) return;
		using UserManagementForm form = new UserManagementForm(db, currentUsername);
		form.ShowDialog(this);
	}

	private static FlowLayoutPanel Bar()
	{
		return new FlowLayoutPanel
		{
			Dock = DockStyle.Top,
			Height = 64,
			Padding = new Padding(10),
			BackColor = Color.White
		};
	}

	private static Button Button(string text, EventHandler click, int width = 120)
	{
		Button button = new Button();
		button.Text = text;
		button.Width = width;
		button.Height = 40;
		button.Margin = new Padding(6, 2, 0, 0);
		button.BackColor = KofcNavy;
		button.ForeColor = Color.White;
		button.Font = new Font("Segoe UI Semibold", 10.5f);
		button.Cursor = Cursors.Hand;
		button.FlatStyle = FlatStyle.Flat;
		button.FlatAppearance.BorderColor = KofcGold;
		button.FlatAppearance.BorderSize = 1;
		button.Click += click;
		return button;
	}

	private static Button HeaderButton(string text, int width)
	{
		Button button = new Button
		{
			Text = text,
			Width = width,
			Height = 38,
			Margin = new Padding(8, 0, 0, 0),
			BackColor = Color.White,
			ForeColor = KofcNavy,
			Font = new Font("Segoe UI Semibold", 10f),
			Cursor = Cursors.Hand,
			FlatStyle = FlatStyle.Flat
		};
		button.FlatAppearance.BorderColor = KofcGold;
		button.FlatAppearance.BorderSize = 1;
		return button;
	}

	private static Label Card()
	{
		return new Label
		{
			AutoSize = false,
			Size = new Size(210, 115),
			Margin = new Padding(0, 0, 15, 0),
			BackColor = Color.White,
			TextAlign = ContentAlignment.MiddleCenter,
			Font = new Font("Segoe UI Semibold", 14f),
			BorderStyle = BorderStyle.FixedSingle
		};
	}

	private static DataGridView Grid()
	{
		return new DataGridView
		{
			Dock = DockStyle.Fill,
			BackgroundColor = Color.White,
			BorderStyle = BorderStyle.None,
			ReadOnly = true,
			AllowUserToAddRows = false,
			AllowUserToDeleteRows = false,
			AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill,
			SelectionMode = DataGridViewSelectionMode.FullRowSelect,
			MultiSelect = false,
			RowHeadersVisible = false,
			RowTemplate = 
			{
				Height = 34
			},
			ColumnHeadersHeight = 42,
			EnableHeadersVisualStyles = false,
			ColumnHeadersDefaultCellStyle = 
			{
				BackColor = Color.FromArgb(114, 23, 36),
				ForeColor = Color.White,
				Font = new Font("Segoe UI Semibold", 11f)
			},
			DefaultCellStyle = 
			{
				Padding = new Padding(5),
				SelectionBackColor = Color.FromArgb(224, 231, 255),
				SelectionForeColor = Color.Black
			}
		};
	}

	private void TryInitialImport()
	{
		string path = Path.Combine(AppContext.BaseDirectory, "Data", "InitialData.xlsx");
		if (db.MemberCount() == 0 && File.Exists(path))
		{
			try
			{
				ExcelService.Import(path, db);
			}
			catch (Exception ex)
			{
				MessageBox.Show("The app opened, but initial Excel import failed:\n" + ex.Message, "Import notice");
			}
		}
	}

	private void RefreshAll()
	{
		RefreshCouncils();
		RefreshCycles();
		RefreshMembers();
		RefreshPayments();
		RefreshCompliance();
		RefreshLedger();
		RefreshDisbursements();
		RefreshDashboard();
	}

	private void RefreshCouncils()
	{
		string text = memberCouncil.Text;
		string text2 = complianceCouncil.Text;
		string[] array = new string[1] { "All Councils" }.Concat(db.GetCouncils()).ToArray();
		memberCouncil.Items.Clear();
		paymentCouncil.Items.Clear();
		complianceCouncil.Items.Clear();
		ComboBox.ObjectCollection items = memberCouncil.Items;
		object[] array2 = array;
		object[] items2 = array2;
		items.AddRange(items2);
		ComboBox.ObjectCollection items3 = paymentCouncil.Items;
		array2 = array;
		items2 = array2;
		items3.AddRange(items2);
		ComboBox.ObjectCollection items4 = complianceCouncil.Items;
		array2 = array;
		items2 = array2;
		items4.AddRange(items2);
		memberCouncil.SelectedItem = (array.Contains(text) ? text : "All Councils");
		paymentCouncil.SelectedIndex = 0;
		complianceCouncil.SelectedItem = (array.Contains(text2) ? text2 : "All Councils");
	}

	private void RefreshMembers()
	{
		members = db.GetMembers(search.Text, (memberCouncil.Text == "") ? "All Councils" : memberCouncil.Text);
		if (memberCycle.SelectedItem is CycleFilterOption { CycleId: long cycleId })
		{
			HashSet<long> cycleMemberIds = db.GetPaymentRows(cycleId).Select((PaymentRow row) => row.MemberId).ToHashSet();
			members = members.Where((Member member) => cycleMemberIds.Contains(member.Id)).ToList();
		}
		memberGrid.DataSource = members.Select((Member x, int index) => new
		{
			No = index + 1,
			Id = x.Id,
			Name = x.FullName,
			Type = x.MembershipType,
			Council = x.Council,
			ContactNumber = x.ContactNumber,
			Status = x.MemberStatus,
			DateOfDeath = x.DateOfDeath?.ToString("MMM d, yyyy"),
			MembershipStartCycle = (cycles.FirstOrDefault((CollectionCycle c) => c.Id == x.StartCycleId)?.Name ?? "Not set"),
			BenefitsClaimed = x.ClaimedBenefits,
			Remarks = x.Remarks
		}).ToList();
		if (memberGrid.Columns.Contains("Id"))
		{
			memberGrid.Columns["Id"].Visible = false;
		}
		StyleNumberColumn(memberGrid);
		if (memberGrid.Columns.Contains("MembershipStartCycle"))
		{
			memberGrid.Columns["MembershipStartCycle"].HeaderText = "Membership Start Cycle";
			memberGrid.Columns["MembershipStartCycle"].FillWeight = 115f;
		}
		if (memberGrid.Columns.Contains("BenefitsClaimed"))
		{
			memberGrid.Columns["BenefitsClaimed"].HeaderText = "Benefits Claimed";
			memberGrid.Columns["BenefitsClaimed"].FillWeight = 135f;
			memberGrid.Columns["BenefitsClaimed"].DefaultCellStyle.WrapMode = DataGridViewTriState.True;
		}
		AddMemberActionColumns();
	}

	private void RefreshCompliance()
	{
		compliance = db.GetComplianceRows((complianceCouncil.Text == "") ? "All Councils" : complianceCouncil.Text);
		IEnumerable<ComplianceRow> enumerable = compliance;
		if (!string.IsNullOrWhiteSpace(complianceSearch.Text))
		{
			enumerable = enumerable.Where((ComplianceRow x) => x.MemberName.Contains(complianceSearch.Text, StringComparison.OrdinalIgnoreCase));
		}
		if (!string.IsNullOrWhiteSpace(complianceRecommendationFilter.Text) && complianceRecommendationFilter.Text != "All Recommendations")
		{
			enumerable = enumerable.Where((ComplianceRow x) => x.Recommendation == complianceRecommendationFilter.Text);
		}
		DataTable dataTable = new DataTable();
		dataTable.Columns.Add("No.", typeof(int));
		dataTable.Columns.Add("MemberId", typeof(long));
		dataTable.Columns.Add("Council");
		dataTable.Columns.Add("Member Name");
		dataTable.Columns.Add("Current Status");
		dataTable.Columns.Add("Annual Fee");
		dataTable.Columns.Add("Consecutive Missed Contributions", typeof(int));
		dataTable.Columns.Add("Unpaid Cycles");
		dataTable.Columns.Add("Good Standing");
		dataTable.Columns.Add("Recommendation");
		dataTable.Columns.Add("Status Reason");
		int num = 1;
		foreach (ComplianceRow item in enumerable)
		{
			dataTable.Rows.Add(num++, item.MemberId, item.Council, item.MemberName, item.CurrentStatus, item.AnnualFee, item.ConsecutiveMissedContributions, item.UnpaidCycles, item.GoodStanding, item.Recommendation, item.StatusReason);
		}
		dataTable.DefaultView.Sort = "Council ASC, [Member Name] ASC";
		complianceGrid.DataSource = dataTable.DefaultView;
		if (complianceGrid.Columns.Contains("MemberId"))
		{
			complianceGrid.Columns["MemberId"].Visible = false;
		}
		StyleNumberColumn(complianceGrid);
		string[] array = new string[3] { "Annual Fee", "Unpaid Cycles", "Status Reason" };
		foreach (string columnName in array)
		{
			if (complianceGrid.Columns.Contains(columnName))
			{
				complianceGrid.Columns[columnName].DefaultCellStyle.WrapMode = DataGridViewTriState.True;
			}
		}
		if (complianceGrid.Columns.Contains("Annual Fee"))
		{
			complianceGrid.Columns["Annual Fee"].FillWeight = 80f;
		}
		if (complianceGrid.Columns.Contains("Unpaid Cycles"))
		{
			complianceGrid.Columns["Unpaid Cycles"].FillWeight = 150f;
		}
		if (complianceGrid.Columns.Contains("Status Reason"))
		{
			complianceGrid.Columns["Status Reason"].FillWeight = 250f;
		}
		complianceGrid.AutoSizeRowsMode = DataGridViewAutoSizeRowsMode.AllCells;
		StyleComplianceRows();
	}

	private void RefreshLedger()
	{
		bankTransactions = db.GetBankTransactions();
		DataTable table = new DataTable();
		table.Columns.Add("No.", typeof(int));
		table.Columns.Add("Id", typeof(long));
		table.Columns.Add("Date");
		table.Columns.Add("Reference");
		table.Columns.Add("Description");
		table.Columns.Add("Deposit", typeof(decimal));
		table.Columns.Add("Withdrawal", typeof(decimal));
		table.Columns.Add("Balance", typeof(decimal));
		table.Columns.Add("Recorded By");
		decimal balance = 0m;
		decimal deposits = 0m;
		decimal withdrawals = 0m;
		int number = 1;
		foreach (BankTransaction transaction in bankTransactions)
		{
			bool isDeposit = transaction.TransactionType.Equals("Deposit", StringComparison.OrdinalIgnoreCase);
			if (isDeposit) { balance += transaction.Amount; deposits += transaction.Amount; }
			else { balance -= transaction.Amount; withdrawals += transaction.Amount; }
			table.Rows.Add(number++, transaction.Id, transaction.TransactionDate.ToString("MMM d, yyyy"), transaction.ReferenceNumber,
				transaction.Description, isDeposit ? transaction.Amount : 0m, isDeposit ? 0m : transaction.Amount, balance, transaction.RecordedBy);
		}
		ledgerGrid.DataSource = table;
		if (ledgerGrid.Columns.Contains("Id")) ledgerGrid.Columns["Id"].Visible = false;
		StyleNumberColumn(ledgerGrid);
		foreach (string column in new[] { "Deposit", "Withdrawal", "Balance" })
			if (ledgerGrid.Columns.Contains(column)) ledgerGrid.Columns[column].DefaultCellStyle.Format = "₱#,##0.00;[Red]-₱#,##0.00";
		if (ledgerGrid.Columns.Contains("Description"))
		{
			ledgerGrid.Columns["Description"].FillWeight = 180f;
			ledgerGrid.Columns["Description"].DefaultCellStyle.WrapMode = DataGridViewTriState.True;
		}
		ledgerGrid.AutoSizeRowsMode = DataGridViewAutoSizeRowsMode.AllCells;
		ledgerBalance.Text = $"BANK BALANCE  ₱{balance:N2}   •   Deposits ₱{deposits:N2}   •   Withdrawals ₱{withdrawals:N2}";
		AddLedgerActionColumns();
	}

	private void AddLedgerActionColumns()
	{
		if (Can("EditLedger") && !ledgerGrid.Columns.Contains("LedgerEditAction"))
			ledgerGrid.Columns.Add(new DataGridViewButtonColumn { Name = "LedgerEditAction", HeaderText = "Edit", Text = "Edit", UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat, FillWeight = 50f, MinimumWidth = 82, DefaultCellStyle = ActionCellStyle(InfoBlue) });
		if (Can("DeleteLedger") && !ledgerGrid.Columns.Contains("LedgerDeleteAction"))
			ledgerGrid.Columns.Add(new DataGridViewButtonColumn { Name = "LedgerDeleteAction", HeaderText = "Delete", Text = "Delete", UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat, FillWeight = 55f, MinimumWidth = 92, DefaultCellStyle = ActionCellStyle(DangerRed) });
	}

	private void RefreshDisbursements()
	{
		disbursements = db.GetDisbursements();
		var summary = db.FinancialSummary();
		decimal runningBalance = summary.totalCollections;
		DataTable table = new DataTable();
		table.Columns.Add("No.", typeof(int)); table.Columns.Add("Id", typeof(long)); table.Columns.Add("Date"); table.Columns.Add("Voucher / Check No.");
		table.Columns.Add("Payee / Recipient"); table.Columns.Add("Category"); table.Columns.Add("Particulars"); table.Columns.Add("Amount", typeof(decimal));
		table.Columns.Add("Fund Balance", typeof(decimal)); table.Columns.Add("Recorded By");
		int number = 1;
		foreach (Disbursement expense in disbursements)
		{
			runningBalance -= expense.Amount;
			table.Rows.Add(number++, expense.Id, expense.DisbursementDate.ToString("MMM d, yyyy"), expense.VoucherNumber, expense.Payee,
				expense.Category, expense.Particulars, expense.Amount, runningBalance, expense.RecordedBy);
		}
		disbursementGrid.DataSource = table;
		if (disbursementGrid.Columns.Contains("Id")) disbursementGrid.Columns["Id"].Visible = false;
		StyleNumberColumn(disbursementGrid);
		foreach (string column in new[] { "Amount", "Fund Balance" }) if (disbursementGrid.Columns.Contains(column))
			disbursementGrid.Columns[column].DefaultCellStyle.Format = "₱#,##0.00;[Red]-₱#,##0.00";
		foreach (string column in new[] { "Payee / Recipient", "Particulars" }) if (disbursementGrid.Columns.Contains(column))
			disbursementGrid.Columns[column].DefaultCellStyle.WrapMode = DataGridViewTriState.True;
		if (disbursementGrid.Columns.Contains("Particulars")) disbursementGrid.Columns["Particulars"].FillWeight = 180f;
		disbursementGrid.AutoSizeRowsMode = DataGridViewAutoSizeRowsMode.AllCells;
		disbursementBalance.Text = $"OPENING COLLECTIONS ₱{summary.totalCollections:N2}   •   EXPENSES ₱{summary.totalExpenses:N2}   •   AVAILABLE FUND ₱{summary.availableFunds:N2}";
		AddDisbursementActionColumns();
	}

	private void AddDisbursementActionColumns()
	{
		if (Can("EditDisbursements") && !disbursementGrid.Columns.Contains("DisbursementEditAction"))
			disbursementGrid.Columns.Add(new DataGridViewButtonColumn { Name = "DisbursementEditAction", HeaderText = "Edit", Text = "Edit", UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat, FillWeight = 50f, MinimumWidth = 82, DefaultCellStyle = ActionCellStyle(InfoBlue) });
		if (Can("DeleteDisbursements") && !disbursementGrid.Columns.Contains("DisbursementDeleteAction"))
			disbursementGrid.Columns.Add(new DataGridViewButtonColumn { Name = "DisbursementDeleteAction", HeaderText = "Delete", Text = "Delete", UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat, FillWeight = 55f, MinimumWidth = 92, DefaultCellStyle = ActionCellStyle(DangerRed) });
	}

	private void StyleComplianceRows()
	{
		foreach (DataGridViewRow item in (IEnumerable)complianceGrid.Rows)
		{
			string text = Convert.ToString(item.Cells["Recommendation"].Value) ?? "";
			string text2 = Convert.ToString(item.Cells["Current Status"].Value) ?? "";
			if (text.Contains("expulsion", StringComparison.OrdinalIgnoreCase))
			{
				item.DefaultCellStyle.BackColor = Color.FromArgb(248, 215, 218);
				item.DefaultCellStyle.ForeColor = Color.FromArgb(132, 32, 41);
				item.DefaultCellStyle.SelectionBackColor = DangerRed;
				item.DefaultCellStyle.SelectionForeColor = Color.White;
			}
			else if (text2.Equals("Inactive", StringComparison.OrdinalIgnoreCase))
			{
				item.DefaultCellStyle.BackColor = Color.FromArgb(255, 243, 205);
				item.DefaultCellStyle.ForeColor = Color.FromArgb(102, 77, 3);
				item.DefaultCellStyle.SelectionBackColor = Color.FromArgb(255, 193, 7);
				item.DefaultCellStyle.SelectionForeColor = Color.Black;
			}
		}
	}

	private void ComplianceGridCellFormatting(object? sender, DataGridViewCellFormattingEventArgs e)
	{
		if (e.RowIndex >= 0 && complianceGrid.Columns.Contains("Recommendation") && complianceGrid.Columns.Contains("Current Status"))
		{
			DataGridViewRow dataGridViewRow = complianceGrid.Rows[e.RowIndex];
			string text = Convert.ToString(dataGridViewRow.Cells["Recommendation"].Value) ?? "";
			string text2 = Convert.ToString(dataGridViewRow.Cells["Current Status"].Value) ?? "";
			if (text.Contains("expulsion", StringComparison.OrdinalIgnoreCase))
			{
				e.CellStyle.BackColor = Color.FromArgb(248, 215, 218);
				e.CellStyle.ForeColor = Color.FromArgb(132, 32, 41);
				e.CellStyle.SelectionBackColor = DangerRed;
				e.CellStyle.SelectionForeColor = Color.White;
			}
			else if (text2.Equals("Inactive", StringComparison.OrdinalIgnoreCase))
			{
				e.CellStyle.BackColor = Color.FromArgb(255, 243, 205);
				e.CellStyle.ForeColor = Color.FromArgb(102, 77, 3);
				e.CellStyle.SelectionBackColor = Color.FromArgb(255, 193, 7);
				e.CellStyle.SelectionForeColor = Color.Black;
			}
		}
	}

	private void RefreshCycles()
	{
		long? selectedCycleId = (paymentCycle.SelectedItem as CollectionCycle)?.Id
			?? (dashboardCycle.SelectedItem as CollectionCycle)?.Id;
		long? memberCycleId = (memberCycle.SelectedItem as CycleFilterOption)?.CycleId;
		cycles = db.GetCycles();
		cycleGrid.DataSource = cycles.Select((CollectionCycle x, int index) => new
		{
			No = index + 1,
			Id = x.Id,
			Name = x.Name,
			Type = x.Type,
			ExpectedAmount = x.ExpectedAmount,
			StartDate = x.StartDate?.ToString("MMM d, yyyy"),
			DueDate = x.DueDate?.ToString("MMM d, yyyy"),
			Status = (x.Active ? "Active" : "Closed")
		}).ToList();
		if (cycleGrid.Columns.Contains("Id"))
		{
			cycleGrid.Columns["Id"].Visible = false;
		}
		StyleNumberColumn(cycleGrid);
		string selectedType = cycles.FirstOrDefault(x => x.Id == selectedCycleId)?.Type
			?? (paymentType.SelectedItem as PaymentTypeOption)?.DatabaseType
			?? "Dayong";
		paymentType.DataSource = null;
		paymentType.DataSource = new List<PaymentTypeOption>
		{
			new PaymentTypeOption("Dayong", "Dayong Cycle"),
			new PaymentTypeOption("Registration Fee", "Registration Fee"),
			new PaymentTypeOption("Annual Dues", "Annual Dues")
		};
		paymentType.DisplayMember = "DisplayName";
		paymentType.ValueMember = "DatabaseType";
		paymentType.SelectedValue = selectedType;
		PopulatePaymentCycles(selectedCycleId);
		dashboardCycle.DataSource = null;
		dashboardCycle.DataSource = new List<CollectionCycle>(cycles);
		dashboardCycle.DisplayMember = "Name";
		dashboardCycle.ValueMember = "Id";
		if (selectedCycleId.HasValue)
		{
			dashboardCycle.SelectedValue = selectedCycleId.Value;
		}
		memberCycle.Items.Clear();
		memberCycle.Items.Add(new CycleFilterOption(null, "All Cycles"));
		foreach (CollectionCycle cycle in cycles)
		{
			memberCycle.Items.Add(new CycleFilterOption(cycle.Id, cycle.Name));
		}
		memberCycle.SelectedItem = memberCycle.Items.Cast<CycleFilterOption>()
			.FirstOrDefault((CycleFilterOption option) => option.CycleId == memberCycleId)
			?? memberCycle.Items[0];
	}

	private void PopulatePaymentCycles(long? preferredCycleId = null)
	{
		string type = (paymentType.SelectedItem as PaymentTypeOption)?.DatabaseType ?? "Dayong";
		long? currentId = preferredCycleId ?? (paymentCycle.SelectedItem as CollectionCycle)?.Id;
		List<CollectionCycle> filteredCycles = cycles.Where(x => x.Type == type).ToList();
		paymentCycle.DataSource = null;
		paymentCycle.DataSource = filteredCycles;
		paymentCycle.DisplayMember = "Name";
		paymentCycle.ValueMember = "Id";
		if (currentId.HasValue && filteredCycles.Any(x => x.Id == currentId.Value)) paymentCycle.SelectedValue = currentId.Value;
	}

	private sealed class PaymentTypeOption
	{
		public string DatabaseType { get; }
		public string DisplayName { get; }
		public PaymentTypeOption(string databaseType, string displayName) { DatabaseType = databaseType; DisplayName = displayName; }
	}

	private sealed class CycleFilterOption
	{
		public long? CycleId { get; }

		private string Name { get; }

		public CycleFilterOption(long? cycleId, string name)
		{
			CycleId = cycleId;
			Name = name;
		}

		public override string ToString() => Name;
	}

	private void RefreshPayments()
	{
		if (!(paymentCycle.SelectedItem is CollectionCycle collectionCycle))
		{
			return;
		}
		IEnumerable<PaymentRow> source = db.GetPaymentRows(collectionCycle.Id, (paymentCouncil.Text == "") ? "All Councils" : paymentCouncil.Text);
		if (!string.IsNullOrWhiteSpace(paymentSearch.Text))
		{
			source = source.Where((PaymentRow x) => x.MemberName.Contains(paymentSearch.Text, StringComparison.OrdinalIgnoreCase));
		}
		if (!string.IsNullOrWhiteSpace(paymentStatusFilter.Text) && paymentStatusFilter.Text != "All")
		{
			source = source.Where((PaymentRow x) => x.Status == paymentStatusFilter.Text);
		}
		payments = source.ToList();
		paymentGrid.DataSource = payments.Select((PaymentRow x, int index) => new
		{
			No = index + 1,
			MemberId = x.MemberId,
			MemberName = x.MemberName,
			Council = x.Council,
			Expected = x.Expected,
			Paid = x.Paid,
			DatePaid = x.DatePaid?.ToString("MMM d, yyyy"),
			ReceiptNumber = x.ReceiptNumber,
			Status = x.Status
		}).ToList();
		if (paymentGrid.Columns.Contains("MemberId"))
		{
			paymentGrid.Columns["MemberId"].Visible = false;
		}
		StyleNumberColumn(paymentGrid);
		string[] array = new string[2] { "Expected", "Paid" };
		foreach (string columnName in array)
		{
			if (paymentGrid.Columns.Contains(columnName))
			{
				paymentGrid.Columns[columnName].DefaultCellStyle.Format = "₱#,##0.00";
			}
		}
		if (Can("RecordPayments") && !paymentGrid.Columns.Contains("PaymentAction"))
		{
			paymentGrid.Columns.Add(new DataGridViewButtonColumn
			{
				Name = "PaymentAction",
				HeaderText = "Action",
				Text = "Record Payment",
				UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat,
				FillWeight = 90f,
				MinimumWidth = 120,
				DefaultCellStyle = ActionCellStyle(SuccessGreen)
			});
		}
		if (Can("RecordPayments") && !paymentGrid.Columns.Contains("DeletePaymentAction"))
		{
			paymentGrid.Columns.Add(new DataGridViewButtonColumn
			{
				Name = "DeletePaymentAction", HeaderText = "Delete", Text = "Delete", UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat, FillWeight = 75f, MinimumWidth = 95,
				DefaultCellStyle = ActionCellStyle(DangerRed)
			});
		}
		RefreshDashboard();
	}

	private void RefreshDashboard()
	{
		long? cycleId = (paymentCycle.SelectedItem as CollectionCycle)?.Id;
		(int, int, decimal, decimal) tuple = db.Dashboard(cycleId);
		var financial = db.FinancialSummary();
		membersCard.Text = $"ACTIVE MEMBERS\n\n{tuple.Item1:N0}";
		collectedCard.Text = $"TOTAL MONEY COLLECTED\n\n₱{financial.totalCollections:N2}";
		registrationCard.Text = $"REGISTRATION FEE • ₱100\n\n{financial.registrationPayers:N0} paid  •  ₱{financial.registrationTotal:N2}";
		annualDuesCard.Text = $"ANNUAL DUES • ₱100\n\n{financial.annualPayers:N0} paid  •  ₱{financial.annualTotal:N2}";
		dayongCollectionsCard.Text = $"DAYONG COLLECTIONS\n\n₱{financial.dayongTotal:N2}";
		expensesCard.Text = $"TOTAL DISBURSEMENTS\n\n₱{financial.totalExpenses:N2}";
		availableFundsCard.Text = $"AVAILABLE DAYONG FUND\n\n₱{financial.availableFunds:N2}";
		paidCard.Text = $"SELECTED CYCLE PAID\n\n{tuple.Item2:N0}";
		balanceCard.Text = $"SELECTED CYCLE DUE\n\n₱{Math.Max(0m, tuple.Item4 - tuple.Item3):N2}";
	}

	private Member? SelectedMember()
	{
		if (memberGrid.CurrentRow == null)
		{
			return null;
		}
		long id = Convert.ToInt64(memberGrid.CurrentRow.Cells["Id"].Value);
		return members.FirstOrDefault((Member x) => x.Id == id);
	}

	private static DataGridViewCellStyle ActionCellStyle(Color color)
	{
		return new DataGridViewCellStyle
		{
			BackColor = color,
			ForeColor = Color.White,
			SelectionBackColor = color,
			SelectionForeColor = Color.White,
			Alignment = DataGridViewContentAlignment.MiddleCenter,
			Font = new Font("Segoe UI Semibold", 10f),
			Padding = new Padding(6, 4, 6, 4)
		};
	}

	private void AddMemberActionColumns()
	{
		if (Can("EditMembers") && !memberGrid.Columns.Contains("EditAction"))
		{
			memberGrid.Columns.Add(new DataGridViewButtonColumn
			{
				Name = "EditAction",
				HeaderText = "Edit",
				Text = "Edit",
				UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat,
				FillWeight = 55f,
				MinimumWidth = 82,
				DefaultCellStyle = ActionCellStyle(InfoBlue)
			});
		}
		if (Can("DeleteMembers") && !memberGrid.Columns.Contains("DeleteAction"))
		{
			memberGrid.Columns.Add(new DataGridViewButtonColumn
			{
				Name = "DeleteAction",
				HeaderText = "Delete",
				Text = "Delete",
				UseColumnTextForButtonValue = true,
				FlatStyle = FlatStyle.Flat,
				FillWeight = 60f,
				MinimumWidth = 92,
				DefaultCellStyle = ActionCellStyle(DangerRed)
			});
		}
	}

	private void MemberGridCellContentClick(object? sender, DataGridViewCellEventArgs e)
	{
		if (e.RowIndex >= 0)
		{
			memberGrid.CurrentCell = memberGrid.Rows[e.RowIndex].Cells[e.ColumnIndex];
			string name = memberGrid.Columns[e.ColumnIndex].Name;
			if (name == "EditAction" && Can("EditMembers"))
			{
				EditMember(sender, EventArgs.Empty);
			}
			else if (name == "DeleteAction" && Can("DeleteMembers"))
			{
				DeleteMember(sender, EventArgs.Empty);
			}
		}
	}

	private CollectionCycle? SelectedCycle()
	{
		if (cycleGrid.CurrentRow == null)
		{
			return null;
		}
		long id = Convert.ToInt64(cycleGrid.CurrentRow.Cells["Id"].Value);
		return cycles.FirstOrDefault((CollectionCycle x) => x.Id == id);
	}

	private void AddMember(object? s, EventArgs e)
	{
		if (!Can("AddMembers")) return;
		using MemberDialog memberDialog = new MemberDialog(null, cycles);
		if (memberDialog.ShowDialog(this) == DialogResult.OK)
		{
			try
			{
				db.SaveMember(memberDialog.Value);
				RefreshAll();
				return;
			}
			catch (Exception ex)
			{
				MessageBox.Show("Could not save member. The same member may already exist.\n" + ex.Message);
				return;
			}
		}
	}

	private void EditMember(object? s, EventArgs e)
	{
		if (!Can("EditMembers")) return;
		Member member = SelectedMember();
		if (member == null)
		{
			return;
		}
		using MemberDialog memberDialog = new MemberDialog(member, cycles);
		if (memberDialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveMember(memberDialog.Value);
			RefreshAll();
		}
	}

	private void DeleteMember(object? s, EventArgs e)
	{
		if (!Can("DeleteMembers")) return;
		Member member = SelectedMember();
		if (member != null && MessageBox.Show("Delete " + member.FullName + " and all payment records?", "Confirm", MessageBoxButtons.YesNo, MessageBoxIcon.Exclamation) == DialogResult.Yes)
		{
			db.DeleteMember(member.Id);
			RefreshAll();
		}
	}

	private void ReviewComplianceMember(object? s, EventArgs e)
	{
		if (!Can("ReviewMemberStatus")) return;
		if (complianceGrid.CurrentRow == null)
		{
			return;
		}
		long id = Convert.ToInt64(complianceGrid.CurrentRow.Cells["MemberId"].Value);
		Member member = db.GetMembers().FirstOrDefault((Member x) => x.Id == id);
		if (member == null)
		{
			return;
		}
		using MemberDialog memberDialog = new MemberDialog(member, cycles);
		if (memberDialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveMember(memberDialog.Value);
			RefreshAll();
		}
	}

	private void AddCycle(object? s, EventArgs e)
	{
		if (!Can("AddCycles")) return;
		using CycleDialog cycleDialog = new CycleDialog();
		if (cycleDialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveCycle(cycleDialog.Value);
			RefreshAll();
		}
	}

	private void EditCycle(object? s, EventArgs e)
	{
		if (!Can("EditCycles")) return;
		CollectionCycle collectionCycle = SelectedCycle();
		if (collectionCycle == null)
		{
			return;
		}
		using CycleDialog cycleDialog = new CycleDialog(collectionCycle);
		if (cycleDialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveCycle(cycleDialog.Value);
			RefreshAll();
		}
	}

	private void DeleteCycle(object? s, EventArgs e)
	{
		if (!Can("DeleteCycles")) return;
		CollectionCycle collectionCycle = SelectedCycle();
		if (collectionCycle != null && MessageBox.Show("Delete " + collectionCycle.Name + " and all its payments?", "Confirm", MessageBoxButtons.YesNo, MessageBoxIcon.Exclamation) == DialogResult.Yes)
		{
			db.DeleteCycle(collectionCycle.Id);
			RefreshAll();
		}
	}

	private void RecordPayment(object? s, EventArgs e)
	{
		if (!Can("RecordPayments")) return;
		if (paymentGrid.CurrentRow == null || !(paymentCycle.SelectedItem is CollectionCycle collectionCycle))
		{
			return;
		}
		long id = Convert.ToInt64(paymentGrid.CurrentRow.Cells["MemberId"].Value);
		PaymentRow selectedPayment = payments.First((PaymentRow x) => x.MemberId == id);
		List<PaymentDue> memberDues = db.GetMemberDues(id, collectionCycle.Id);
		using PaymentDialog paymentDialog = new PaymentDialog(selectedPayment.MemberName, memberDues);
		if (paymentDialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SavePayments(id, paymentDialog.Allocations, paymentDialog.DatePaid, paymentDialog.ReceiptNumber);
			RefreshPayments();
		}
	}

	private void PaymentGridCellContentClick(object? sender, DataGridViewCellEventArgs e)
	{
		if (!Can("RecordPayments") || e.RowIndex < 0 || e.ColumnIndex < 0) return;
		string action = paymentGrid.Columns[e.ColumnIndex].Name;
		if (action == "PaymentAction")
		{
			paymentGrid.CurrentCell = paymentGrid.Rows[e.RowIndex].Cells["MemberName"];
			RecordPayment(sender, EventArgs.Empty);
		}
		else if (action == "DeletePaymentAction")
		{
			paymentGrid.CurrentCell = paymentGrid.Rows[e.RowIndex].Cells["MemberName"];
			long memberId = Convert.ToInt64(paymentGrid.Rows[e.RowIndex].Cells["MemberId"].Value);
			PaymentRow payment = payments.First(x => x.MemberId == memberId);
			if (payment.PaymentId == 0 || payment.Paid <= 0m)
			{
				MessageBox.Show("This member has no recorded payment in the selected cycle.", "Nothing to Delete", MessageBoxButtons.OK, MessageBoxIcon.Information);
				return;
			}
			using PaymentDeleteAuthenticationDialog authentication = new PaymentDeleteAuthenticationDialog(db, currentUsername);
			if (authentication.ShowDialog(this) != DialogResult.OK) return;
			CollectionCycle cycle = (CollectionCycle)paymentCycle.SelectedItem;
			string receiptText = string.IsNullOrWhiteSpace(payment.ReceiptNumber) ? "No receipt number" : "Receipt " + payment.ReceiptNumber;
			if (MessageBox.Show($"Permanently delete this payment?\n\nMember: {payment.MemberName}\nCycle: {cycle.Name}\nAmount: ₱{payment.Paid:N2}\n{receiptText}", "Confirm Payment Deletion", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) != DialogResult.Yes) return;
			try
			{
				if (!db.DeletePayment(memberId, cycle.Id))
				{
					MessageBox.Show("The payment was not found. Refresh the list and try again.", "Payment Not Deleted", MessageBoxButtons.OK, MessageBoxIcon.Warning);
					return;
				}
				RefreshPayments();
				MessageBox.Show("Payment deleted successfully.", "Payment Deleted", MessageBoxButtons.OK, MessageBoxIcon.Information);
			}
			catch (Exception ex)
			{
				MessageBox.Show("Could not delete the payment.\n" + ex.Message, "Payment Not Deleted", MessageBoxButtons.OK, MessageBoxIcon.Error);
			}
		}
	}

	private BankTransaction? SelectedBankTransaction()
	{
		if (ledgerGrid.CurrentRow == null || !ledgerGrid.Columns.Contains("Id")) return null;
		long id = Convert.ToInt64(ledgerGrid.CurrentRow.Cells["Id"].Value);
		return bankTransactions.FirstOrDefault(x => x.Id == id);
	}

	private void AddDeposit(object? sender, EventArgs e) => AddBankTransaction("Deposit");

	private void AddWithdrawal(object? sender, EventArgs e) => AddBankTransaction("Withdrawal");

	private void AddBankTransaction(string transactionType)
	{
		if (!Can("AddLedger")) return;
		using BankTransactionDialog dialog = new BankTransactionDialog(null, transactionType);
		if (dialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveBankTransaction(dialog.Value, currentUsername);
			RefreshLedger();
		}
	}

	private void EditBankTransaction(object? sender, EventArgs e)
	{
		if (!Can("EditLedger")) return;
		BankTransaction? transaction = SelectedBankTransaction();
		if (transaction == null) return;
		using BankTransactionDialog dialog = new BankTransactionDialog(transaction);
		if (dialog.ShowDialog(this) == DialogResult.OK)
		{
			db.SaveBankTransaction(dialog.Value, currentUsername);
			RefreshLedger();
		}
	}

	private void DeleteBankTransaction(object? sender, EventArgs e)
	{
		if (!Can("DeleteLedger")) return;
		BankTransaction? transaction = SelectedBankTransaction();
		if (transaction == null) return;
		string prompt = $"Delete this {transaction.TransactionType.ToLowerInvariant()} of ₱{transaction.Amount:N2}?\n\n{transaction.Description}";
		if (MessageBox.Show(prompt, "Confirm ledger deletion", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) == DialogResult.Yes)
		{
			db.DeleteBankTransaction(transaction.Id);
			RefreshLedger();
		}
	}

	private void LedgerGridCellContentClick(object? sender, DataGridViewCellEventArgs e)
	{
		if (e.RowIndex < 0 || e.ColumnIndex < 0) return;
		ledgerGrid.CurrentCell = ledgerGrid.Rows[e.RowIndex].Cells[e.ColumnIndex];
		string column = ledgerGrid.Columns[e.ColumnIndex].Name;
		if (column == "LedgerEditAction") EditBankTransaction(sender, EventArgs.Empty);
		else if (column == "LedgerDeleteAction") DeleteBankTransaction(sender, EventArgs.Empty);
	}

	private Disbursement? SelectedDisbursement()
	{
		if (disbursementGrid.CurrentRow == null || !disbursementGrid.Columns.Contains("Id")) return null;
		long id = Convert.ToInt64(disbursementGrid.CurrentRow.Cells["Id"].Value);
		return disbursements.FirstOrDefault(x => x.Id == id);
	}

	private void AddDisbursement(object? sender, EventArgs e)
	{
		if (!Can("AddDisbursements")) return;
		using DisbursementDialog dialog = new DisbursementDialog();
		if (dialog.ShowDialog(this) == DialogResult.OK) { db.SaveDisbursement(dialog.Value, currentUsername); RefreshAll(); }
	}

	private void EditDisbursement(object? sender, EventArgs e)
	{
		if (!Can("EditDisbursements")) return;
		Disbursement? expense = SelectedDisbursement(); if (expense == null) return;
		using DisbursementDialog dialog = new DisbursementDialog(expense);
		if (dialog.ShowDialog(this) == DialogResult.OK) { db.SaveDisbursement(dialog.Value, currentUsername); RefreshAll(); }
	}

	private void DeleteDisbursement(object? sender, EventArgs e)
	{
		if (!Can("DeleteDisbursements")) return;
		Disbursement? expense = SelectedDisbursement(); if (expense == null) return;
		if (MessageBox.Show($"Delete this expense of ₱{expense.Amount:N2}?\n\n{expense.Payee}\n{expense.Particulars}", "Confirm expense deletion", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) == DialogResult.Yes)
		{ db.DeleteDisbursement(expense.Id); RefreshAll(); }
	}

	private void DisbursementGridCellContentClick(object? sender, DataGridViewCellEventArgs e)
	{
		if (e.RowIndex < 0 || e.ColumnIndex < 0) return;
		disbursementGrid.CurrentCell = disbursementGrid.Rows[e.RowIndex].Cells[e.ColumnIndex];
		string column = disbursementGrid.Columns[e.ColumnIndex].Name;
		if (column == "DisbursementEditAction") EditDisbursement(sender, EventArgs.Empty);
		else if (column == "DisbursementDeleteAction") DeleteDisbursement(sender, EventArgs.Empty);
	}

	private void DrawWebTab(object? sender, DrawItemEventArgs e)
	{
		Rectangle bounds = e.Bounds;
		bool flag = (e.State & DrawItemState.Selected) == DrawItemState.Selected;
		using SolidBrush brush = new SolidBrush(flag ? KofcRed : KofcNavy);
		e.Graphics.FillRectangle(brush, bounds);
		if (flag)
		{
			e.Graphics.FillRectangle(new SolidBrush(KofcGold), new Rectangle(bounds.Left, bounds.Bottom - 4, bounds.Width, 4));
		}
		TextRenderer.DrawText(e.Graphics, tabs.TabPages[e.Index].Text, new Font("Segoe UI Semibold", 10.5f), bounds, Color.White, TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter);
	}

	private static void StyleNumberColumn(DataGridView grid)
	{
		string columnName = (grid.Columns.Contains("No.") ? "No." : "No");
		if (grid.Columns.Contains(columnName))
		{
			grid.Columns[columnName].FillWeight = 35f;
			grid.Columns[columnName].MinimumWidth = 48;
			grid.Columns[columnName].DefaultCellStyle.Alignment = DataGridViewContentAlignment.MiddleCenter;
		}
	}

	private void ImportExcel(object? s, EventArgs e)
	{
		if (!Can("ImportExcel")) return;
		OpenFileDialog openFileDialog = new OpenFileDialog
		{
			Filter = "Excel workbook (*.xlsx)|*.xlsx"
		};
		try
		{
			if (openFileDialog.ShowDialog() != DialogResult.OK)
			{
				return;
			}
			try
			{
				(int, int) tuple = ExcelService.Import(openFileDialog.FileName, db);
				MessageBox.Show($"Import complete.\nMembers processed: {tuple.Item1}\nPayments processed: {tuple.Item2}");
				RefreshAll();
			}
			catch (Exception ex)
			{
				MessageBox.Show("Import failed:\n" + ex.Message);
			}
		}
		finally
		{
			((IDisposable)(object)openFileDialog)?.Dispose();
		}
	}

	private void ExportExcel(object? s, EventArgs e)
	{
		if (!Can("ExportExcel")) return;
		SaveFileDialog saveFileDialog = new SaveFileDialog
		{
			Filter = "Excel workbook (*.xlsx)|*.xlsx",
			FileName = $"Dayong Export {DateTime.Today:yyyy-MM-dd}.xlsx"
		};
		try
		{
			if (saveFileDialog.ShowDialog() != DialogResult.OK)
			{
				return;
			}
			try
			{
				ExcelService.Export(saveFileDialog.FileName, db);
				MessageBox.Show("Excel export completed.");
			}
			catch (Exception ex)
			{
				MessageBox.Show("Export failed:\n" + ex.Message);
			}
		}
		finally
		{
			((IDisposable)(object)saveFileDialog)?.Dispose();
		}
	}

	private void Backup(object? s, EventArgs e)
	{
		if (!Can("BackupDatabase")) return;
		SaveFileDialog saveFileDialog = new SaveFileDialog
		{
			Filter = "Dayong database (*.db)|*.db",
			FileName = $"dayong-backup-{DateTime.Now:yyyyMMdd-HHmm}.db"
		};
		try
		{
			if (saveFileDialog.ShowDialog() == DialogResult.OK)
			{
				db.Backup(saveFileDialog.FileName);
				MessageBox.Show("Backup completed.");
			}
		}
		finally
		{
			((IDisposable)(object)saveFileDialog)?.Dispose();
		}
	}

	private void OpenFolder(object? s, EventArgs e)
	{
		if (!Can("OpenDataFolder")) return;
		Process.Start(new ProcessStartInfo
		{
			FileName = Path.GetDirectoryName(db.DatabasePath),
			UseShellExecute = true
		});
	}

	private void OpenBylaws(object? s, EventArgs e)
	{
		if (!Can("OpenBylaws")) return;
		string text = Path.Combine(AppContext.BaseDirectory, "Data", "KCLDA-Bylaws.docx");
		if (File.Exists(text))
		{
			Process.Start(new ProcessStartInfo
			{
				FileName = text,
				UseShellExecute = true
			});
		}
		else
		{
			MessageBox.Show("The by-laws document was not found in the Data folder.");
		}
	}

	private void PrintReport(object? s, EventArgs e)
	{
		if (!Can("PrintTreasurerReport")) return;
		if (!(paymentCycle.SelectedItem is CollectionCycle collectionCycle))
		{
			return;
		}
		reportPayments = db.GetPaymentRows(collectionCycle.Id, string.IsNullOrWhiteSpace(paymentCouncil.Text) ? "All Councils" : paymentCouncil.Text);
		printRow = 0;
		using PrintPreviewDialog printPreviewDialog = new PrintPreviewDialog
		{
			Document = printDocument,
			Width = 1100,
			Height = 750
		};
		printPreviewDialog.ShowDialog(this);
	}

	private void PrintLedger(object? sender, EventArgs e)
	{
		if (!Can("PrintLedger")) return;
		bankTransactions = db.GetBankTransactions();
		ledgerPrintRow = 0;
		ledgerPrintBalance = 0m;
		using PrintPreviewDialog preview = new PrintPreviewDialog { Document = ledgerPrintDocument, Width = 1100, Height = 750 };
		preview.ShowDialog(this);
	}

	private void PrintLedgerPage(object? sender, PrintPageEventArgs e)
	{
		Graphics graphics = e.Graphics;
		float y = e.MarginBounds.Top;
		using Font title = new Font("Segoe UI", 15f, FontStyle.Bold);
		using Font heading = new Font("Segoe UI", 11f, FontStyle.Bold);
		using Font body = new Font("Segoe UI", 8.5f);
		graphics.DrawString("KNIGHTS OF COLUMBUS LOCAL DAYONG ASSOCIATION", heading, Brushes.Black, e.MarginBounds.Left, y);
		y += 24f;
		graphics.DrawString("BANK DEPOSIT AND WITHDRAWAL LEDGER", title, Brushes.Black, e.MarginBounds.Left, y);
		y += 32f;
		decimal totalDeposits = bankTransactions.Where(x => x.TransactionType == "Deposit").Sum(x => x.Amount);
		decimal totalWithdrawals = bankTransactions.Where(x => x.TransactionType == "Withdrawal").Sum(x => x.Amount);
		graphics.DrawString($"Report date: {DateTime.Now:MMMM d, yyyy}     Deposits: ₱{totalDeposits:N2}     Withdrawals: ₱{totalWithdrawals:N2}     Balance: ₱{totalDeposits - totalWithdrawals:N2}", body, Brushes.Black, e.MarginBounds.Left, y);
		y += 30f;
		float left = e.MarginBounds.Left;
		graphics.DrawString("Date", heading, Brushes.Black, left, y);
		graphics.DrawString("Reference", heading, Brushes.Black, left + 85, y);
		graphics.DrawString("Description", heading, Brushes.Black, left + 170, y);
		graphics.DrawString("Deposit", heading, Brushes.Black, left + 410, y);
		graphics.DrawString("Withdrawal", heading, Brushes.Black, left + 500, y);
		graphics.DrawString("Balance", heading, Brushes.Black, left + 600, y);
		y += 22f; graphics.DrawLine(Pens.Black, left, y, e.MarginBounds.Right, y); y += 8f;
		while (ledgerPrintRow < bankTransactions.Count)
		{
			if (y + 24f > e.MarginBounds.Bottom) { e.HasMorePages = true; return; }
			BankTransaction transaction = bankTransactions[ledgerPrintRow];
			bool deposit = transaction.TransactionType == "Deposit";
			ledgerPrintBalance += deposit ? transaction.Amount : -transaction.Amount;
			graphics.DrawString(transaction.TransactionDate.ToString("MMM d, yyyy"), body, Brushes.Black, left, y);
			graphics.DrawString(transaction.ReferenceNumber, body, Brushes.Black, left + 85, y);
			graphics.DrawString(ShortText(transaction.Description, 39), body, Brushes.Black, left + 170, y);
			if (deposit) graphics.DrawString($"₱{transaction.Amount:N2}", body, Brushes.Black, left + 410, y);
			else graphics.DrawString($"₱{transaction.Amount:N2}", body, Brushes.Black, left + 500, y);
			graphics.DrawString($"₱{ledgerPrintBalance:N2}", body, Brushes.Black, left + 600, y);
			y += 22f; ledgerPrintRow++;
		}
		y += 8f; graphics.DrawLine(Pens.Black, left, y, e.MarginBounds.Right, y); y += 12f;
		graphics.DrawString($"Certified bank ledger balance: ₱{ledgerPrintBalance:N2}", heading, Brushes.Black, left, y);
		y += 55f; graphics.DrawLine(Pens.Black, left, y, left + 240, y);
		graphics.DrawString("Prepared by: Treasurer", body, Brushes.Black, left, y + 5f);
		graphics.DrawLine(Pens.Black, e.MarginBounds.Right - 240, y, e.MarginBounds.Right, y);
		graphics.DrawString("Date", body, Brushes.Black, e.MarginBounds.Right - 240, y + 5f);
		e.HasMorePages = false; ledgerPrintRow = 0; ledgerPrintBalance = 0m;
	}

	private static string ShortText(string value, int length) => value.Length <= length ? value : value.Substring(0, length - 1) + "…";

	private void PrintDisbursements(object? sender, EventArgs e)
	{
		if (!Can("PrintDisbursements")) return;
		disbursements = db.GetDisbursements(); disbursementPrintRow = 0; disbursementPrintBalance = db.FinancialSummary().totalCollections;
		using PrintPreviewDialog preview = new PrintPreviewDialog { Document = disbursementPrintDocument, Width = 1100, Height = 750 };
		preview.ShowDialog(this);
	}

	private void PrintDisbursementPage(object? sender, PrintPageEventArgs e)
	{
		Graphics graphics = e.Graphics; float y = e.MarginBounds.Top; float left = e.MarginBounds.Left;
		using Font title = new Font("Segoe UI", 15f, FontStyle.Bold); using Font heading = new Font("Segoe UI", 10f, FontStyle.Bold); using Font body = new Font("Segoe UI", 8.3f);
		var summary = db.FinancialSummary();
		graphics.DrawString("KNIGHTS OF COLUMBUS LOCAL DAYONG ASSOCIATION", heading, Brushes.Black, left, y); y += 24f;
		graphics.DrawString("DISBURSEMENT LEDGER", title, Brushes.Black, left, y); y += 32f;
		graphics.DrawString($"Report date: {DateTime.Now:MMMM d, yyyy}     Total collections from start: ₱{summary.totalCollections:N2}     Expenses: ₱{summary.totalExpenses:N2}     Available fund: ₱{summary.availableFunds:N2}", body, Brushes.Black, left, y); y += 30f;
		graphics.DrawString("Date", heading, Brushes.Black, left, y); graphics.DrawString("Voucher/Check", heading, Brushes.Black, left + 80, y);
		graphics.DrawString("Payee", heading, Brushes.Black, left + 175, y); graphics.DrawString("Particulars", heading, Brushes.Black, left + 300, y);
		graphics.DrawString("Amount", heading, Brushes.Black, left + 520, y); graphics.DrawString("Fund Balance", heading, Brushes.Black, left + 610, y);
		y += 22f; graphics.DrawLine(Pens.Black, left, y, e.MarginBounds.Right, y); y += 8f;
		while (disbursementPrintRow < disbursements.Count)
		{
			if (y + 24f > e.MarginBounds.Bottom) { e.HasMorePages = true; return; }
			Disbursement expense = disbursements[disbursementPrintRow]; disbursementPrintBalance -= expense.Amount;
			graphics.DrawString(expense.DisbursementDate.ToString("MMM d, yyyy"), body, Brushes.Black, left, y);
			graphics.DrawString(ShortText(expense.VoucherNumber, 15), body, Brushes.Black, left + 80, y);
			graphics.DrawString(ShortText(expense.Payee, 20), body, Brushes.Black, left + 175, y);
			graphics.DrawString(ShortText(expense.Particulars, 36), body, Brushes.Black, left + 300, y);
			graphics.DrawString($"₱{expense.Amount:N2}", body, Brushes.Black, left + 520, y);
			graphics.DrawString($"₱{disbursementPrintBalance:N2}", body, Brushes.Black, left + 610, y);
			y += 22f; disbursementPrintRow++;
		}
		y += 8f; graphics.DrawLine(Pens.Black, left, y, e.MarginBounds.Right, y); y += 12f;
		graphics.DrawString($"Certified available Dayong fund: ₱{summary.availableFunds:N2}", heading, Brushes.Black, left, y); y += 55f;
		graphics.DrawLine(Pens.Black, left, y, left + 240, y); graphics.DrawString("Prepared by: Treasurer", body, Brushes.Black, left, y + 5f);
		graphics.DrawLine(Pens.Black, e.MarginBounds.Right - 240, y, e.MarginBounds.Right, y); graphics.DrawString("Date", body, Brushes.Black, e.MarginBounds.Right - 240, y + 5f);
		e.HasMorePages = false; disbursementPrintRow = 0; disbursementPrintBalance = summary.totalCollections;
	}

	private void PrintPage(object? s, PrintPageEventArgs e)
	{
		if (!(paymentCycle.SelectedItem is CollectionCycle collectionCycle))
		{
			return;
		}
		Graphics graphics = e.Graphics;
		float num = e.MarginBounds.Top;
		using Font font = new Font("Segoe UI", 15f, FontStyle.Bold);
		using Font font2 = new Font("Segoe UI", 11f, FontStyle.Bold);
		using Font font3 = new Font("Segoe UI", 9f);
		graphics.DrawString("KNIGHTS OF COLUMBUS LOCAL DAYONG ASSOCIATION", font2, Brushes.Black, e.MarginBounds.Left, num);
		num += 24f;
		graphics.DrawString("TREASURER'S COLLECTION REPORT", font, Brushes.Black, e.MarginBounds.Left, num);
		num += 32f;
		graphics.DrawString($"Cycle: {collectionCycle.Name}     Council: {paymentCouncil.Text}     Report date: {DateTime.Now:MMMM d, yyyy}", font3, Brushes.Black, e.MarginBounds.Left, num);
		num += 25f;
		decimal num2 = reportPayments.Sum((PaymentRow x) => x.Expected);
		decimal num3 = reportPayments.Sum((PaymentRow x) => x.Paid);
		decimal value = Math.Max(0m, num2 - num3);
		int value2 = reportPayments.Count((PaymentRow x) => x.Status == "Paid");
		int value3 = reportPayments.Count((PaymentRow x) => x.Status == "Partial");
		int value4 = reportPayments.Count((PaymentRow x) => x.Status == "Unpaid");
		graphics.FillRectangle(new SolidBrush(Color.FromArgb(242, 244, 247)), e.MarginBounds.Left, num, e.MarginBounds.Width, 48f);
		graphics.DrawString($"Expected: ₱{num2:N2}     Collected: ₱{num3:N2}     Outstanding: ₱{value:N2}", font2, Brushes.Black, e.MarginBounds.Left + 8, num + 5f);
		graphics.DrawString($"Members: {reportPayments.Count}     Paid: {value2}     Partial: {value3}     Unpaid: {value4}", font3, Brushes.Black, e.MarginBounds.Left + 8, num + 28f);
		num += 62f;
		graphics.DrawString("Member", font3, Brushes.Black, e.MarginBounds.Left, num);
		graphics.DrawString("Council", font3, Brushes.Black, e.MarginBounds.Left + 245, num);
		graphics.DrawString("Expected", font3, Brushes.Black, e.MarginBounds.Left + 345, num);
		graphics.DrawString("Paid", font3, Brushes.Black, e.MarginBounds.Left + 430, num);
		graphics.DrawString("Balance", font3, Brushes.Black, e.MarginBounds.Left + 505, num);
		graphics.DrawString("Status", font3, Brushes.Black, e.MarginBounds.Left + 590, num);
		num += 22f;
		graphics.DrawLine(Pens.Black, e.MarginBounds.Left, num, e.MarginBounds.Right, num);
		num += 8f;
		while (printRow < reportPayments.Count)
		{
			PaymentRow paymentRow = reportPayments[printRow];
			if (num + 24f > (float)e.MarginBounds.Bottom)
			{
				e.HasMorePages = true;
				return;
			}
			graphics.DrawString(paymentRow.MemberName, font3, Brushes.Black, e.MarginBounds.Left, num);
			graphics.DrawString(paymentRow.Council, font3, Brushes.Black, e.MarginBounds.Left + 245, num);
			graphics.DrawString($"₱{paymentRow.Expected:N2}", font3, Brushes.Black, e.MarginBounds.Left + 345, num);
			graphics.DrawString($"₱{paymentRow.Paid:N2}", font3, Brushes.Black, e.MarginBounds.Left + 430, num);
			graphics.DrawString($"₱{Math.Max(0m, paymentRow.Expected - paymentRow.Paid):N2}", font3, Brushes.Black, e.MarginBounds.Left + 505, num);
			graphics.DrawString(paymentRow.Status, font3, Brushes.Black, e.MarginBounds.Left + 590, num);
			num += 22f;
			printRow++;
		}
		num += 10f;
		graphics.DrawLine(Pens.Black, e.MarginBounds.Left, num, e.MarginBounds.Right, num);
		num += 10f;
		graphics.DrawString($"Certified total collected: ₱{num3:N2}     Outstanding balance: ₱{value:N2}", font2, Brushes.Black, e.MarginBounds.Left, num);
		num += 55f;
		graphics.DrawLine(Pens.Black, e.MarginBounds.Left, num, e.MarginBounds.Left + 240, num);
		graphics.DrawString("Prepared by: Treasurer", font3, Brushes.Black, e.MarginBounds.Left, num + 5f);
		graphics.DrawLine(Pens.Black, e.MarginBounds.Right - 240, num, e.MarginBounds.Right, num);
		graphics.DrawString("Date", font3, Brushes.Black, e.MarginBounds.Right - 240, num + 5f);
		e.HasMorePages = false;
		printRow = 0;
	}
}
