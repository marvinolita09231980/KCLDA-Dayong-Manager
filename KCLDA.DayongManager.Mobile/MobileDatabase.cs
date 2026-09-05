using System.Security.Cryptography;
using System.Text;
using SQLite;

namespace KCLDA.DayongManager.Mobile;

public sealed class MobileDatabase
{
	private SQLiteAsyncConnection db = null!;
	private bool ready;
	public async Task InitializeAsync()
	{
		if (ready) return;
		string databasePath = Path.Combine(FileSystem.AppDataDirectory, "dayong-mobile.db");
		if (!File.Exists(databasePath)) await CopySeedAsync(databasePath);
		db = new SQLiteAsyncConnection(databasePath);
		await db.CreateTableAsync<MobileUser>(); await db.CreateTableAsync<MobileMember>(); await db.CreateTableAsync<MobileCycle>(); await db.CreateTableAsync<MobilePayment>();
		if (await db.Table<MobileMember>().CountAsync() == 0) await ImportSeedAsync();
		if (await db.Table<MobileUser>().CountAsync() == 0) await db.InsertAsync(new MobileUser { Username = "admin", PasswordHash = Hash("Dayong@2026") });
		ready = true;
	}
	private static async Task CopySeedAsync(string destination)
	{
		await using Stream source = await FileSystem.OpenAppPackageFileAsync("seed-dayong.db");
		await using FileStream output = File.Create(destination);
		await source.CopyToAsync(output);
	}
	private async Task ImportSeedAsync()
	{
		string tempPath = Path.Combine(FileSystem.CacheDirectory, "seed-dayong.db");
		await CopySeedAsync(tempPath);
		var seed = new SQLiteAsyncConnection(tempPath);
		await db.InsertAllAsync(await seed.Table<MobileMember>().ToListAsync());
		await db.InsertAllAsync(await seed.Table<MobileCycle>().ToListAsync());
		await db.InsertAllAsync(await seed.Table<MobilePayment>().ToListAsync());
		await seed.CloseAsync(); File.Delete(tempPath);
	}
	public async Task<bool> AuthenticateAsync(string username, string password) { await InitializeAsync(); var u = await db.Table<MobileUser>().Where(x => x.Username == username).FirstOrDefaultAsync(); return u != null && CryptographicOperations.FixedTimeEquals(Convert.FromHexString(u.PasswordHash), Convert.FromHexString(Hash(password))); }
	private static string Hash(string value) => Convert.ToHexString(SHA256.HashData(Encoding.UTF8.GetBytes(value)));
	public async Task<List<MobileMember>> MembersAsync() { await InitializeAsync(); return await db.Table<MobileMember>().OrderBy(x => x.LastName).ToListAsync(); }
	public async Task SaveMemberAsync(MobileMember m) { await InitializeAsync(); if (m.Id == 0) await db.InsertAsync(m); else await db.UpdateAsync(m); }
	public async Task<List<MobileCycle>> CyclesAsync() { await InitializeAsync(); return await db.Table<MobileCycle>().OrderByDescending(x => x.Id).ToListAsync(); }
	public async Task SaveCycleAsync(MobileCycle c) { await InitializeAsync(); if (c.Id == 0) await db.InsertAsync(c); else await db.UpdateAsync(c); }
	public async Task<List<MobilePayment>> PaymentsAsync() { await InitializeAsync(); return await db.Table<MobilePayment>().ToListAsync(); }
	public async Task<List<MobileDue>> DuesAsync(MobileMember member)
	{
		var cycles = await CyclesAsync(); var payments = (await PaymentsAsync()).Where(x => x.MemberId == member.Id).ToList();
		return cycles.Where(c => c.Active && (c.Type != "Dayong" || member.StartCycleId == null || c.Id >= member.StartCycleId)
			&& (member.DateOfDeath == null || c.StartDate == null || c.StartDate.Value.Date <= member.DateOfDeath.Value.Date)
			&& (c.Type != "Registration Fee" || payments.Any(p => p.CycleId == c.Id) || c.StartDate?.Year == member.RegistrationDate.Year || c.DueDate?.Year == member.RegistrationDate.Year))
			.Select(c => new MobileDue(c, payments.FirstOrDefault(p => p.CycleId == c.Id)?.Amount ?? 0)).ToList();
	}
	public async Task SavePaymentAsync(int memberId, int cycleId, decimal amount, DateTime paid, string receipt)
	{
		var p = await db.Table<MobilePayment>().Where(x => x.MemberId == memberId && x.CycleId == cycleId).FirstOrDefaultAsync();
		if (p == null) await db.InsertAsync(new MobilePayment { MemberId = memberId, CycleId = cycleId, Amount = amount, DatePaid = paid, ReceiptNumber = receipt });
		else { p.Amount = amount; p.DatePaid = paid; p.ReceiptNumber = receipt; await db.UpdateAsync(p); }
	}
	public async Task<bool> DeletePaymentAsync(int memberId, int cycleId) { var p = await db.Table<MobilePayment>().Where(x => x.MemberId == memberId && x.CycleId == cycleId).FirstOrDefaultAsync(); return p != null && await db.DeleteAsync(p) > 0; }
	public async Task<LanSyncRequest> SyncUploadAsync()
	{
		return new LanSyncRequest { Payments = (await PaymentsAsync()).Select(p => new LanSyncPayment { Id=p.Id,MemberId=p.MemberId,CycleId=p.CycleId,Amount=p.Amount,DatePaid=p.DatePaid,ReceiptNumber=p.ReceiptNumber }).ToList() };
	}
	public async Task ApplySyncAsync(LanSyncSnapshot snapshot)
	{
		await InitializeAsync();
		await db.DeleteAllAsync<MobilePayment>(); await db.DeleteAllAsync<MobileCycle>(); await db.DeleteAllAsync<MobileMember>();
		await db.InsertAllAsync(snapshot.Members.Select(m => new MobileMember { Id=(int)m.Id,LastName=m.LastName,FirstName=m.FirstName,Council=m.Council,RegistrationDate=m.RegistrationDate,StartCycleId=(int?)m.StartCycleId,Status=m.Status,DateOfDeath=m.DateOfDeath }));
		await db.InsertAllAsync(snapshot.Cycles.Select(c => new MobileCycle { Id=(int)c.Id,Name=c.Name,Type=c.Type,ExpectedAmount=c.ExpectedAmount,StartDate=c.StartDate,DueDate=c.DueDate,Active=c.Active }));
		await db.InsertAllAsync(snapshot.Payments.Select(p => new MobilePayment { Id=(int)p.Id,MemberId=(int)p.MemberId,CycleId=(int)p.CycleId,Amount=p.Amount,DatePaid=p.DatePaid,ReceiptNumber=p.ReceiptNumber }));
	}
}
