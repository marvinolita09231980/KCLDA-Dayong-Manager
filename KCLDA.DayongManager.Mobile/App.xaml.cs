namespace KCLDA.DayongManager.Mobile;

public partial class App : Application
{
	public App(MobileDatabase database)
	{
		InitializeComponent();

		MainPage = new NavigationPage(new LoginPage(database));
	}
}
