using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace DayongManager;

public static class AppUpdateService
{
	public const string CurrentVersion = "1.1.1";
	private const string LatestReleaseApi = "https://api.github.com/repos/marvinolita09231980/KCLDA-Dayong-Manager/releases/latest";
	private static readonly HttpClient Client = CreateClient();

	public static async Task CheckAsync(IWin32Window owner, bool silentWhenCurrent)
	{
		try
		{
			using HttpResponseMessage response = await Client.GetAsync(LatestReleaseApi);
			if (!response.IsSuccessStatusCode)
			{
				if (!silentWhenCurrent) MessageBox.Show(owner, "No published GitHub release was found yet. Publish the first release in the KCLDA-Dayong-Manager repository, then check again.", "Updates", MessageBoxButtons.OK, MessageBoxIcon.Information);
				return;
			}
			string json = await response.Content.ReadAsStringAsync();
			ReleaseInfo? release = JsonSerializer.Deserialize<ReleaseInfo>(json, new JsonSerializerOptions { PropertyNameCaseInsensitive = true });
			if (release == null || !TryVersion(release.TagName, out Version? latest) || !TryVersion(CurrentVersion, out Version? current))
			{
				if (!silentWhenCurrent) MessageBox.Show(owner, "The latest GitHub release does not contain a valid version tag such as v1.1.1.", "Updates");
				return;
			}
			if (latest <= current)
			{
				if (!silentWhenCurrent) MessageBox.Show(owner, $"KCLDA Dayong Manager {CurrentVersion} is already up to date.", "Updates", MessageBoxButtons.OK, MessageBoxIcon.Information);
				return;
			}
			ReleaseAsset? asset = release.Assets.FirstOrDefault(x => x.Name.Equals("KCLDA-Dayong-Manager-Update.zip", StringComparison.OrdinalIgnoreCase))
				?? release.Assets.FirstOrDefault(x => x.Name.EndsWith(".zip", StringComparison.OrdinalIgnoreCase) && !x.Name.Contains("Source", StringComparison.OrdinalIgnoreCase));
			if (asset == null || string.IsNullOrWhiteSpace(asset.BrowserDownloadUrl))
			{
				MessageBox.Show(owner, "A newer release exists, but it has no Windows update ZIP. Upload it using the filename KCLDA-Dayong-Manager-Update.zip.", "Updates", MessageBoxButtons.OK, MessageBoxIcon.Warning);
				return;
			}
			if (MessageBox.Show(owner, $"KCLDA Dayong Manager {latest} is available.\n\nDownload and install it now?", "Update available", MessageBoxButtons.YesNo, MessageBoxIcon.Information) != DialogResult.Yes) return;
			await DownloadAndLaunchAsync(asset.BrowserDownloadUrl, asset.Digest);
			Application.Exit();
		}
		catch (Exception ex)
		{
			if (!silentWhenCurrent) MessageBox.Show(owner, "Could not check for updates. Confirm that this computer can access GitHub.\n\n" + ex.Message, "Update check failed", MessageBoxButtons.OK, MessageBoxIcon.Warning);
		}
	}

	private static async Task DownloadAndLaunchAsync(string url, string digest)
	{
		string folder = Path.Combine(Path.GetTempPath(), "KCLDA-Dayong-Update-" + Guid.NewGuid().ToString("N"));
		Directory.CreateDirectory(folder); string zipPath = Path.Combine(folder, "update.zip");
		using HttpResponseMessage response = await Client.GetAsync(url, HttpCompletionOption.ResponseHeadersRead); response.EnsureSuccessStatusCode();
		await using (FileStream output = File.Create(zipPath)) await response.Content.CopyToAsync(output);
		if (digest.StartsWith("sha256:", StringComparison.OrdinalIgnoreCase))
		{
			await using FileStream input = File.OpenRead(zipPath); string actual = Convert.ToHexString(await SHA256.HashDataAsync(input));
			string expected = digest.Substring("sha256:".Length);
			if (!actual.Equals(expected, StringComparison.OrdinalIgnoreCase)) throw new InvalidDataException("The downloaded update failed its SHA-256 integrity check.");
		}
		string extracted = Path.Combine(folder, "files"); ZipFile.ExtractToDirectory(zipPath, extracted);
		string? installer = Directory.GetFiles(extracted, "Install-Dayong-Manager.bat", SearchOption.AllDirectories).FirstOrDefault();
		if (installer == null) throw new InvalidDataException("The update ZIP does not contain Install-Dayong-Manager.bat.");
		Process.Start(new ProcessStartInfo { FileName = installer, WorkingDirectory = Path.GetDirectoryName(installer), UseShellExecute = true, Verb = "runas" });
	}

	private static HttpClient CreateClient()
	{
		HttpClient client = new HttpClient { Timeout = TimeSpan.FromMinutes(10) };
		client.DefaultRequestHeaders.UserAgent.ParseAdd("KCLDA-Dayong-Manager/" + CurrentVersion);
		client.DefaultRequestHeaders.Accept.ParseAdd("application/vnd.github+json"); return client;
	}

	private static bool TryVersion(string value, out Version? version) => Version.TryParse(value.Trim().TrimStart('v', 'V'), out version);

	private sealed class ReleaseInfo
	{
		[JsonPropertyName("tag_name")]
		public string TagName { get; set; } = "";
		public List<ReleaseAsset> Assets { get; set; } = new List<ReleaseAsset>();
	}

	private sealed class ReleaseAsset
	{
		public string Name { get; set; } = "";
		[JsonPropertyName("browser_download_url")]
		public string BrowserDownloadUrl { get; set; } = "";
		public string Digest { get; set; } = "";
	}
}
