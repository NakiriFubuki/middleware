# 🤝 Contributing to PDMS

Thank you for considering contributing to the **Parcel Delivery Management System (PDMS)**! Your help makes this project better. 💖

## 🧾 Table of Contents
- [How to Contribute](#-how-to-contribute)
- [Contribution Guidelines](#-contribution-guidelines)
- [Pull Request Process](#-pull-request-process)
- [Reporting Issues](#-reporting-issues)
- [License](#-license)

---

## 🏢 How to Contribute

1. **Fork the repository** 📚
2. **Clone your fork**
   ```bash
   git clone https://github.com/NakiriFubuki/middleware.git
   cd middleware
   ```
3. **Create a new branch** 🌿
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Set up the local environment**
   - Install XAMPP (Apache + MySQL + PHP)
   - Import `sql/schema.sql` into MySQL
   - Configure `config/config.local.php` if needed
5. **Make your changes** ✨
6. **Test thoroughly**
   - Admin login / parcel create / assign
   - Rider online + GPS updates
   - Map tracking + reports
7. **Commit your changes** 💾
   ```bash
   git commit -m "Add: meaningful commit message"
   ```
8. **Push to your branch** 🚀
   ```bash
   git push origin feature/your-feature-name
   ```
9. **Open a Pull Request** 🛠️

---

## 📋 Contribution Guidelines

- Follow existing naming conventions and file structure.
- Prefer prepared statements for SQL queries.
- Sanitize user output to prevent XSS.
- Keep UI consistent with the current design system.
- Write clear commit messages (`Add:`, `Fix:`, `Update:`).
- Do **not** commit secrets (`config.local.php`, passwords, `.env`).

---

## ✅ Pull Request Process

- Branch from `main` (or `master`).
- Keep PRs focused on one feature/fix.
- Include screenshots for UI changes.
- Link related issues when possible.
- Wait for review/approval before merging.

---

## 🐛 Reporting Issues

Found a bug or have a feature idea? Open an Issue and include:

- Steps to reproduce
- Expected vs actual behavior
- Browser / PHP / MySQL version
- Screenshots if useful

---

## 📝 License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).

Happy Coding! 💻🎉
