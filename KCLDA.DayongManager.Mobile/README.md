# KCLDA Dayong Manager for Android

Native Android/offline version of the KCLDA membership and collection manager.

## Install

1. Copy `KCLDA-Dayong-Manager-Android.apk` to the Android phone.
2. Uninstall version 1.0 if it was previously installed (that package used developer Fast Deployment).
3. Open the replacement APK and allow installation from this source when Android asks.
4. Sign in initially with `admin` / `Dayong@2026`.

## Included

- Offline SQLite database stored privately by Android
- First-run database seeded with the current Windows members, cycles, and payments
- Login and authenticated payment deletion
- Dashboard collection totals
- Member registration and member list
- Collection-cycle creation
- Cascading collection type and cycle filters
- Registration Fee, Annual Dues, and Dayong collection types
- Complete collectable-account view with Paid, Partial, and Unpaid status
- Multi-due and partial payment allocation
- Receipt number and payment date
- Same-Wi-Fi LAN synchronization with the Windows app

## Current data behavior

The APK includes a snapshot of the Windows database taken when the APK was built. It remains fully usable offline. When Windows and Android are on the same Wi-Fi, select **Start LAN Sync** in the Windows app, then enter its address and six-digit code on Android's **Sync** tab. Android uploads collected payments first and then downloads the merged Windows members, cycles, and payments.

The Windows app is authoritative for member and cycle administration. LAN Sync currently transfers Android payments to Windows and refreshes the complete Android dataset from Windows.

To refresh the embedded snapshot before rebuilding, run `tools/create_mobile_seed.py` with the Windows database path and `KCLDA.DayongManager.Mobile/Resources/Raw/seed-dayong.db` as its destination.

## Build

The project targets `net8.0-android`. It requires .NET 8 with the `maui-android` workload, Android SDK 34, and JDK 17.
