# ExportScheduler for PrestaShop

**ExportScheduler** is a powerful PrestaShop module that allows merchants to automate the export of order data. Easily generate CSV or XLSX reports on a schedule or on demand. Perfect for backups, analytics, or syncing with external systems.

---

![Screenshot](https://github.com/arshidkv12/ExportScheduler-for-prestashop/blob/main/docs/Screenshot-1.png?raw=true)

## ✨ Features

- Export order data in **CSV** or **XLSX** format.
- Schedule exports:
  - Daily
  - Weekly
  - Monthly
  - Yearly
- Manual export option for all available orders.
- Supports PrestaShop multi-store environments.
- Easy-to-use admin interface.
- Compatible with PrestaShop 1.7+.

---

Feel free to email me at info@mailmug.net — I’m available to customize PrestaShop for you.


## 📦 Installation

1. Download the module as a ZIP file.
2. In your PrestaShop admin panel, go to **Modules > Module Manager**.
3. Click **Upload a module**, then select the ZIP file.
4. Install and configure the module from the list.

---

## ⚙️ Usage

1. Go to **Modules > ExportScheduler** in your admin dashboard.
2. Add order columns
3. Set up the export frequency.
4. Click **Save**.


### Export URLs

You can export data using the following URLs.
Replace https://your-domain.com with your actual store URL.

**CSV Export:**

```bash
https://your-domain.com/module/exportscheduler/exports?
token={key}&csv=1&interval={interval-type}
```

**XLSX Export:**

```bash
https://your-domain.com/module/exportscheduler/exports?
token={key}&xlsx=1&interval=daily
```

**To Download Immediately:**
**CSV:**
```bash
https://your-domain.com/module/exportscheduler/exports?token={key}
&csv=1&download=1
```

**XLSX:**
```bash
https://your-domain.com/module/exportscheduler/exports?token={key}
&xlsx=1&download=1
```

**Important:**
To get your {key}, go to the module configuration in the Back Office. The token is displayed there securely.

---

## 📁 Output Example

The exported file includes the following order details:

- Order ID
- Customer Name
- Email
- Order Total
- Payment Method
- Order Status
- Order Date
- Shipping Address
- Product List

---

## 📅 Use Cases

- Daily reporting and analytics.
- Integration with third-party accounting tools.
- Backing up order data.
- Sending order exports to vendors or partners.

---

## ⏱️ Step-by-Step: Setup Cron Job in cPanel

### 1. Log into cPanel
- Open: `https://yourdomain.com/cpanel`
- Use your hosting credentials

### 2. Go to “Cron Jobs”
- Under the **Advanced** section
- Click **Cron Jobs**

### 3. Choose the Schedule
In the **“Add New Cron Job”** section:

| Interval | Example  | Cron Expression | Description                    |
|----------|----------|------------------|--------------------------------|
| Daily    | `0 0 * * *`  | Every day at midnight         |
| Weekly   | `0 0 * * 0`  | Every Sunday                  |
| Monthly  | `0 0 1 * *`  | 1st day of each month         |
| Yearly   | `0 0 1 1 *`  | 1st January                   |

Or simply use the **Common Settings** dropdown for convenience.

### 4. Enter the Command

Use `wget` (recommended) or `curl` to call the export URL:

#### Using `wget`:
```bash
wget -q -O /dev/null "https://your-domain.com/module/exportscheduler/exports?token=YOUR_TOKEN&csv=1&download=1"
```

### 5. Click “Add New Cron Job”
You're done! Your server will now trigger exports automatically based on the schedule you defined.

**🗂 Exported files are stored in:**

```bash
{PrestaShop root}/download/exportscheduler/
```

---

## 🤝 Contributing

Feel free to fork this repository and submit pull requests or open issues. Feedback and contributions are welcome!

---

## 📄 License

MIT License – see the [LICENSE](LICENSE) file for details.
