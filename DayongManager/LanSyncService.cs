using System;
using System.Linq;
using System.Net;
using System.Net.NetworkInformation;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Hosting;

namespace DayongManager;

public sealed class LanSyncService : IAsyncDisposable
{
	private readonly DatabaseService database; private WebApplication? app;
	public string Code { get; } = RandomNumberGenerator.GetInt32(100000, 1000000).ToString();
	public string Address => $"http://{LocalAddress()}:5288";
	public LanSyncService(DatabaseService db) { database = db; }
	public async Task StartAsync()
	{
		if (app != null) return;
		var builder = WebApplication.CreateSlimBuilder(); builder.WebHost.UseUrls("http://0.0.0.0:5288"); app = builder.Build();
		app.MapGet("/health", () => Results.Ok(new { app="KCLDA Dayong Manager", ready=true }));
		app.MapPost("/sync", (HttpRequest request, LanSyncRequest data) =>
		{
			if (request.Headers["X-Sync-Code"] != Code) return Results.Unauthorized();
			return Results.Ok(database.SynchronizeMobilePayments(data.Payments));
		});
		await app.StartAsync();
	}
	private static string LocalAddress() => NetworkInterface.GetAllNetworkInterfaces().Where(n => n.OperationalStatus == OperationalStatus.Up && n.NetworkInterfaceType != NetworkInterfaceType.Loopback).SelectMany(n => n.GetIPProperties().UnicastAddresses).FirstOrDefault(a => a.Address.AddressFamily == AddressFamily.InterNetwork && !IPAddress.IsLoopback(a.Address))?.Address.ToString() ?? "127.0.0.1";
	public async ValueTask DisposeAsync() { if (app != null) { await app.StopAsync(); await app.DisposeAsync(); app = null; } }
}
