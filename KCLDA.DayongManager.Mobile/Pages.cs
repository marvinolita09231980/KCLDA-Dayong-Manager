using System.Net.Http.Json;

namespace KCLDA.DayongManager.Mobile;

static class Ui
{
	public static readonly Color Navy = Color.FromArgb("#002F5F"); public static readonly Color Red = Color.FromArgb("#BE1E2D"); public static readonly Color Gold = Color.FromArgb("#F5BE2D");
	public static Button Button(string text, EventHandler action, Color? color = null) { var b = new Button { Text = text, BackgroundColor = color ?? Navy, TextColor = Colors.White, CornerRadius = 10 }; b.Clicked += action; return b; }
	public static Label Title(string text) => new() { Text = text, FontSize = 24, FontAttributes = FontAttributes.Bold, TextColor = Navy };
}

public sealed class LoginPage : ContentPage
{
	public LoginPage(MobileDatabase db)
	{
		Title = "Sign In"; BackgroundColor = Color.FromArgb("#F5F7FA");
		var user = new Entry { Placeholder = "Username", Text = "admin" }; var password = new Entry { Placeholder = "Password", IsPassword = true }; var error = new Label { TextColor = Ui.Red };
		var signIn = Ui.Button("Sign In", async (_, _) => { if (await db.AuthenticateAsync(user.Text?.Trim() ?? "", password.Text ?? "")) Application.Current!.MainPage = new MobileShell(db, user.Text!.Trim()); else { error.Text = "Incorrect username or password."; password.Focus(); } });
		Content = new ScrollView { Content = new VerticalStackLayout { Padding = 30, Spacing = 16, VerticalOptions = LayoutOptions.Center, Children = { new Label { Text = "KCLDA", FontSize = 38, FontAttributes = FontAttributes.Bold, TextColor = Ui.Gold }, Ui.Title("Dayong Manager"), new Label { Text = "Android • Offline database", TextColor = Colors.Gray }, user, password, error, signIn, new Label { Text = "Initial account: admin / Dayong@2026", FontSize = 12, TextColor = Colors.Gray } } } };
	}
}

public sealed class MobileShell : TabbedPage
{
	public MobileShell(MobileDatabase db, string username)
	{
		BarBackgroundColor = Ui.Navy; BarTextColor = Colors.White;
		Children.Add(new NavigationPage(new DashboardPage(db)) { Title = "Dashboard" });
		Children.Add(new NavigationPage(new MembersPage(db)) { Title = "Members" });
		Children.Add(new NavigationPage(new CollectionsPage(db, username)) { Title = "Collections" });
		Children.Add(new NavigationPage(new CyclesPage(db)) { Title = "Cycles" });
		Children.Add(new NavigationPage(new SyncPage(db)) { Title = "Sync" });
	}
}

public sealed class DashboardPage : ContentPage
{
	private readonly MobileDatabase db; private readonly VerticalStackLayout body = new() { Padding = 20, Spacing = 12 };
	public DashboardPage(MobileDatabase database) { db = database; Title = "Dashboard"; Content = new ScrollView { Content = body }; }
	protected override async void OnAppearing() { base.OnAppearing(); var members = await db.MembersAsync(); var payments = await db.PaymentsAsync(); var cycles = await db.CyclesAsync(); body.Clear(); body.Add(Ui.Title("Collection Overview")); body.Add(Card("ACTIVE MEMBERS", members.Count(x => x.Status == "Active").ToString())); body.Add(Card("TOTAL COLLECTED", $"₱{payments.Sum(x => x.Amount):N2}")); body.Add(Card("DAYONG COLLECTIONS", $"₱{payments.Join(cycles, p => p.CycleId, c => c.Id, (p,c) => new {p,c}).Where(x => x.c.Type == "Dayong").Sum(x => x.p.Amount):N2}")); body.Add(Card("RECEIPTED PAYMENTS", payments.Count(x => !string.IsNullOrWhiteSpace(x.ReceiptNumber)).ToString())); }
	private static Border Card(string label, string value) => new() { Stroke = Color.FromArgb("#D8DEE8"), Padding = 18, StrokeShape = new Microsoft.Maui.Controls.Shapes.RoundRectangle { CornerRadius = 12 }, Content = new VerticalStackLayout { Children = { new Label { Text = label, TextColor = Colors.Gray }, new Label { Text = value, FontSize = 27, FontAttributes = FontAttributes.Bold, TextColor = Ui.Navy } } } };
}

public sealed class MembersPage : ContentPage
{
	private readonly MobileDatabase db; private readonly VerticalStackLayout list = new() { Spacing = 8 };
	public MembersPage(MobileDatabase database) { db = database; Title = "Members"; ToolbarItems.Add(new ToolbarItem("Add", null, async () => await Navigation.PushAsync(new MemberEditPage(db)))); Content = new ScrollView { Content = new VerticalStackLayout { Padding = 16, Children = { Ui.Title("Members"), list } } }; }
	protected override async void OnAppearing() { base.OnAppearing(); list.Clear(); foreach (var m in await db.MembersAsync()) list.Add(new Border { Padding = 12, Stroke = Colors.LightGray, Content = new VerticalStackLayout { Children = { new Label { Text = m.FullName, FontAttributes = FontAttributes.Bold }, new Label { Text = $"{m.Council} • {m.Status}", TextColor = Colors.Gray } } } }); }
}

public sealed class MemberEditPage : ContentPage
{
	public MemberEditPage(MobileDatabase db)
	{
		Title = "Add Member"; var last = new Entry { Placeholder = "Last name" }; var first = new Entry { Placeholder = "First name" }; var council = new Entry { Placeholder = "Council" }; var registered = new DatePicker { Date = DateTime.Today };
		Content = new ScrollView { Content = new VerticalStackLayout { Padding = 20, Spacing = 12, Children = { Ui.Title("New Member"), last, first, council, new Label { Text = "Registration date" }, registered, Ui.Button("Save Member", async (_, _) => { if (string.IsNullOrWhiteSpace(last.Text) || string.IsNullOrWhiteSpace(first.Text) || string.IsNullOrWhiteSpace(council.Text)) { await DisplayAlert("Required", "Enter the member's name and council.", "OK"); return; } await db.SaveMemberAsync(new MobileMember { LastName = last.Text.Trim(), FirstName = first.Text.Trim(), Council = council.Text.Trim(), RegistrationDate = registered.Date }); await Navigation.PopAsync(); }) } } };
	}
}

public sealed class CyclesPage : ContentPage
{
	private readonly MobileDatabase db; private readonly VerticalStackLayout list = new() { Spacing = 8 };
	public CyclesPage(MobileDatabase database) { db = database; Title = "Cycles"; ToolbarItems.Add(new ToolbarItem("Add", null, Add)); Content = new ScrollView { Content = new VerticalStackLayout { Padding = 16, Children = { Ui.Title("Collection Cycles"), list } } }; }
	protected override async void OnAppearing() { base.OnAppearing(); await Refresh(); }
	private async Task Refresh() { list.Clear(); foreach (var c in await db.CyclesAsync()) list.Add(new Border { Padding = 12, Stroke = Colors.LightGray, Content = new VerticalStackLayout { Children = { new Label { Text = c.Name, FontAttributes = FontAttributes.Bold }, new Label { Text = $"{c.Type} • ₱{c.ExpectedAmount:N2} • {(c.Active ? "Collectable" : "Closed")}", TextColor = Colors.Gray } } } }); }
	private async void Add() { string? name = await DisplayPromptAsync("New Cycle", "Cycle name"); if (string.IsNullOrWhiteSpace(name)) return; string type = await DisplayActionSheet("Collection type", "Cancel", null, "Dayong", "Registration Fee", "Annual Dues"); if (type == "Cancel") return; string? amountText = await DisplayPromptAsync("Expected Amount", "Amount", keyboard: Keyboard.Numeric); if (!decimal.TryParse(amountText, out decimal amount) || amount <= 0) return; await db.SaveCycleAsync(new MobileCycle { Name = name.Trim(), Type = type, ExpectedAmount = amount, StartDate = DateTime.Today, Active = true }); await Refresh(); }
}

public sealed class CollectionsPage : ContentPage
{
	private readonly MobileDatabase db; private readonly string username; private readonly Picker type = new() { Title = "Collection Type" }; private readonly Picker cycle = new() { Title = "Cycle" }; private readonly VerticalStackLayout rows = new() { Spacing = 8 }; private List<MobileCycle> cycles = new();
	public CollectionsPage(MobileDatabase database, string currentUser) { db = database; username = currentUser; Title = "Collections"; type.ItemsSource = new[] { "Dayong", "Registration Fee", "Annual Dues" }; type.SelectedIndexChanged += (_, _) => Cascade(); cycle.SelectedIndexChanged += async (_, _) => await RefreshRows(); Content = new ScrollView { Content = new VerticalStackLayout { Padding = 14, Spacing = 10, Children = { Ui.Title("Collections & Dues"), type, cycle, rows } } }; }
	protected override async void OnAppearing() { base.OnAppearing(); cycles = await db.CyclesAsync(); if (type.SelectedIndex < 0) type.SelectedIndex = 0; Cascade(); }
	private void Cascade() { string selected = type.SelectedItem?.ToString() ?? "Dayong"; cycle.ItemsSource = cycles.Where(x => x.Type == selected).ToList(); if (cycle.Items.Count > 0) cycle.SelectedIndex = 0; else rows.Clear(); }
	private async Task RefreshRows() { rows.Clear(); if (cycle.SelectedItem is not MobileCycle selected) return; var members = await db.MembersAsync(); var payments = await db.PaymentsAsync(); foreach (var m in members) { var p = payments.FirstOrDefault(x => x.MemberId == m.Id && x.CycleId == selected.Id); string status = p == null || p.Amount == 0 ? "Unpaid" : p.Amount >= selected.ExpectedAmount ? "Paid" : "Partial"; var button = Ui.Button($"{m.FullName}\n{status} • ₱{p?.Amount ?? 0:N2}", async (_, _) => await Navigation.PushAsync(new PaymentPage(db, m, selected, username))); rows.Add(button); } }
}

public sealed class PaymentPage : ContentPage
{
	private readonly MobileDatabase db; private readonly MobileMember member; private readonly MobileCycle selected; private readonly string username; private readonly VerticalStackLayout duesLayout = new() { Spacing = 8 }; private readonly Entry receipt = new() { Placeholder = "Official receipt number" }; private readonly DatePicker paidDate = new() { Date = DateTime.Today }; private readonly List<(MobileDue Due, CheckBox Check, Entry Amount)> controls = new();
	public PaymentPage(MobileDatabase database, MobileMember m, MobileCycle cycle, string currentUser) { db = database; member = m; selected = cycle; username = currentUser; Title = "Record Payment"; Content = new ScrollView { Content = new VerticalStackLayout { Padding = 16, Spacing = 12, Children = { Ui.Title(member.FullName), new Label { Text = "All active collectables", TextColor = Colors.Gray }, duesLayout, receipt, paidDate, Ui.Button("Save Payment", Save), Ui.Button("Delete Selected-Cycle Payment", Delete, Ui.Red) } } }; }
	protected override async void OnAppearing()
	{
		base.OnAppearing(); controls.Clear(); duesLayout.Clear();
		foreach (var d in await db.DuesAsync(member))
		{
			var check = new CheckBox { IsChecked = d.Cycle.Id == selected.Id && d.Balance > 0 };
			var amount = new Entry { Keyboard = Keyboard.Numeric, Text = check.IsChecked ? d.Balance.ToString("0.00") : "0.00", WidthRequest = 100 };
			var description = new Label { Text = $"{d.Cycle.Type}\n{d.Cycle.Name}\n{d.Status} • Balance ₱{d.Balance:N2}", Margin = new Thickness(8, 0) };
			var grid = new Grid { ColumnDefinitions = { new ColumnDefinition(GridLength.Auto), new ColumnDefinition(GridLength.Star), new ColumnDefinition(110) } };
			grid.Add(check, 0); grid.Add(description, 1); grid.Add(amount, 2);
			controls.Add((d, check, amount)); duesLayout.Add(new Border { Padding = 10, Stroke = Colors.LightGray, Content = grid });
		}
	}
	private async void Save(object? s, EventArgs e) { if (string.IsNullOrWhiteSpace(receipt.Text)) { await DisplayAlert("Required", "Enter a receipt number.", "OK"); return; } var chosen = controls.Where(x => x.Check.IsChecked).ToList(); if (chosen.Count == 0) { await DisplayAlert("Required", "Select at least one due.", "OK"); return; } foreach (var x in chosen) { if (!decimal.TryParse(x.Amount.Text, out decimal amount) || amount <= 0 || amount > x.Due.Balance) { await DisplayAlert("Invalid amount", $"Check the amount for {x.Due.Cycle.Name}.", "OK"); return; } await db.SavePaymentAsync(member.Id, x.Due.Cycle.Id, x.Due.Paid + amount, paidDate.Date, receipt.Text.Trim()); } await DisplayAlert("Saved", "Payment recorded.", "OK"); await Navigation.PopAsync(); }
	private async void Delete(object? s, EventArgs e) { string? password = await PasswordPromptPage.PromptAsync(Navigation, username); if (password == null || !await db.AuthenticateAsync(username, password)) { await DisplayAlert("Denied", "Incorrect password.", "OK"); return; } if (!await DisplayAlert("Delete Payment", $"Delete {member.FullName}'s payment for {selected.Name}?", "Delete", "Cancel")) return; if (await db.DeletePaymentAsync(member.Id, selected.Id)) { await DisplayAlert("Deleted", "Payment deleted.", "OK"); await Navigation.PopAsync(); } else await DisplayAlert("Not found", "No payment exists for the selected cycle.", "OK"); }
}

public sealed class PasswordPromptPage : ContentPage
{
	private readonly TaskCompletionSource<string?> completion = new();
	private PasswordPromptPage(string username)
	{
		Title = "Authentication Required"; var password = new Entry { Placeholder = "Password", IsPassword = true };
		Content = new VerticalStackLayout { Padding = 25, Spacing = 14, VerticalOptions = LayoutOptions.Center, Children = { Ui.Title("Confirm Your Identity"), new Label { Text = "Enter the password for " + username }, password, Ui.Button("Authenticate", async (_, _) => { completion.TrySetResult(password.Text); await Navigation.PopModalAsync(); }), Ui.Button("Cancel", async (_, _) => { completion.TrySetResult(null); await Navigation.PopModalAsync(); }, Colors.Gray) } };
	}
	protected override bool OnBackButtonPressed() { completion.TrySetResult(null); return base.OnBackButtonPressed(); }
	public static async Task<string?> PromptAsync(INavigation navigation, string username) { var page = new PasswordPromptPage(username); await navigation.PushModalAsync(new NavigationPage(page)); return await page.completion.Task; }
}

public sealed class SyncPage : ContentPage
{
	private readonly MobileDatabase db;
	private readonly Entry address = new() { Placeholder = "Windows address, e.g. http://192.168.1.5:5288" };
	private readonly Entry code = new() { Placeholder = "6-digit sync code", Keyboard = Keyboard.Numeric, IsPassword = true };
	private readonly Label status = new() { TextColor = Colors.Gray };
	public SyncPage(MobileDatabase database)
	{
		db = database; Title = "LAN Sync"; address.Text = Preferences.Get("sync_address", ""); code.Text = Preferences.Get("sync_code", "");
		Content = new ScrollView { Content = new VerticalStackLayout { Padding = 20, Spacing = 14, Children = { Ui.Title("Sync with Windows"), new Label { Text = "1. On Windows, open Import, Export & Backup and select Start LAN Sync.\n2. Enter the displayed address and code below.\n3. Keep both devices on the same Wi-Fi, then tap Sync Now.", TextColor = Colors.Gray }, address, code, Ui.Button("Sync Now", Sync), status } } };
	}
	private async void Sync(object? sender, EventArgs e)
	{
		string server = (address.Text ?? "").Trim().TrimEnd('/'); string syncCode = (code.Text ?? "").Trim();
		if (!Uri.TryCreate(server, UriKind.Absolute, out _) || syncCode.Length != 6) { await DisplayAlert("Check settings", "Enter the complete Windows address and 6-digit sync code.", "OK"); return; }
		Preferences.Set("sync_address", server); Preferences.Set("sync_code", syncCode); status.Text = "Uploading phone payments…";
		try
		{
			using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(30) }; client.DefaultRequestHeaders.Add("X-Sync-Code", syncCode);
			using var response = await client.PostAsJsonAsync(server + "/sync", await db.SyncUploadAsync());
			if (response.StatusCode == System.Net.HttpStatusCode.Unauthorized) { status.Text = "Authentication failed."; await DisplayAlert("Wrong code", "The sync code does not match the Windows app.", "OK"); return; }
			response.EnsureSuccessStatusCode(); status.Text = "Downloading merged Windows data…";
			LanSyncSnapshot? snapshot = await response.Content.ReadFromJsonAsync<LanSyncSnapshot>();
			if (snapshot == null) throw new InvalidOperationException("The Windows app returned no data.");
			await db.ApplySyncAsync(snapshot); status.Text = $"Synced {snapshot.Members.Count} members, {snapshot.Cycles.Count} cycles, and {snapshot.Payments.Count} payments at {DateTime.Now:t}.";
			await DisplayAlert("Sync complete", status.Text, "OK");
		}
		catch (Exception ex) { status.Text = "Sync failed: " + ex.Message; await DisplayAlert("Cannot sync", "Confirm both devices are on the same Wi-Fi, keep LAN Sync running on Windows, and allow Dayong Manager through Windows Firewall.\n\n" + ex.Message, "OK"); }
	}
}
