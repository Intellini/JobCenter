# JobCenter - Simplified Shop Floor Interface

A zero-training tablet interface for manufacturing shop floor operators. Built with simplicity and reliability as core principles.

## 🎯 Overview

JobCenter is a complete rewrite of a manufacturing job management system, designed specifically for tablet use on the shop floor. The interface requires zero training - if it needs explanation, it's too complex.

## ✨ Features

- **Single Screen Interface** - Everything visible at once, no navigation required
- **Large Touch Targets** - Designed for use with gloved hands (minimum 44px)
- **Offline Capable** - Progressive Web App that works without network connection
- **Visual Progress** - See job status and progress at a glance
- **Bilingual Support** - Hindi/English labels for accessibility
- **Real-time Updates** - Auto-refresh for status changes and QC holds
- **Session Management** - Persistent operator context with localStorage

## 🛠️ Technical Stack

- **Backend**: PHP 7.4+ with REST API
- **Frontend**: Vanilla JavaScript (ES6+) and modern CSS
- **Database**: MySQL/MariaDB
- **Architecture**: Progressive Web App (PWA)
- **No Frameworks**: Intentionally built without frameworks for simplicity

## 📂 Project Structure

```
/jc/
├── api/                  # REST API endpoints
│   └── actions/         # Individual action handlers
├── assets/              # CSS, JS, images
│   ├── css/            # Stylesheets
│   └── js/             # JavaScript modules
├── config/              # Configuration files
├── docs/                # Documentation
├── views/               # UI templates
├── index.php            # Main entry point
├── job.php              # Job detail view
├── planning.php         # Supervisor planning interface
└── timeline.php         # Timeline view component
```

## 🚀 Quick Start

### Requirements
- PHP 7.4 or higher
- MySQL/MariaDB
- Modern web browser with ES6 support
- Web server (Apache/Nginx)

### Installation

1. Clone the repository to your web root:
```bash
git clone https://github.com/Intellini/JobCenter.git /var/www/html/jc
```

2. Configure database connection in `/config/database.php`

3. Set up database credentials in `/dbcon/conn.php`:
```php
$j_srv = 'your_server';
$j_usr = 'your_username';
$j_pwd = 'your_password';
$j_db = 'ptpl';
```

4. Access the application:
- Operator View: `http://[server]/jc/?m=[MACHINE_CODE]`
- Supervisor Planning: `http://[server]/jc/planning.php?m=[MACHINE_CODE]`

## 📱 Usage

### For Operators
1. Access the interface with your machine code: `/jc/?m=HYD85`
2. Select your operator ID from the dropdown
3. View your job timeline and current job
4. Use action buttons to log activities (Setup, FPQC, Start, Pause, Complete, etc.)

### For Supervisors
1. Access planning interface: `/jc/planning.php?m=HYD85`
2. Drag and drop job cards to reorder
3. Add, edit, or delete jobs as needed
4. Exit back to operator view when done

## 🎨 Design Principles

1. **Simplicity First** - If it needs explanation, it's too complex
2. **Touch Optimized** - Minimum 44px touch targets for gloved hands
3. **Visual Feedback** - Clear status indicators and progress bars
4. **Offline First** - Queue actions when offline, sync when connected
5. **Fast** - Under 2 second load time target
6. **Single Screen** - No navigation, everything accessible immediately

## 📊 Key Features

### Operator Workflow
- **Setup Phase**: Prepare machine for job
- **First Piece QC (FPQC)**: Request quality check on first piece
- **Production**: Track progress with visual indicators
- **Completion**: Log actual quantity produced
- **Additional Actions**: Pause, Resume, Breakdown, QC Check, etc.

### Session Management
- Persistent operator context using localStorage
- 8-hour session timeout
- Contact Supervisor with integrated logout option

### Offline Capability
- Actions queued in localStorage when offline
- Automatic sync when connection restored
- Visual indicators for offline status

## 📋 API Endpoints

- `GET /api/job/{id}` - Get job details
- `POST /api/action` - Log operator action
- `GET /api/status/{id}` - Get job status
- `POST /api/quantity` - Update quantity
- `POST /api/actions/*` - Individual action endpoints

## 🧪 Testing

### Test Machines Available
- **Primary**: HYD85 (Machine ID: 13)
- **Secondary**: PNU83 (Machine ID: 9)
- **Alternative**: PNU65 (Machine ID: 3)

### Testing Checklist
1. ✓ Login as operator
2. ✓ View jobs on timeline
3. ✓ Test all 12 operator actions
4. ✓ Verify offline queue functionality
5. ✓ Check auto-refresh for QC holds
6. ✓ Test supervisor planning interface
7. ✓ Verify session persistence

## 📈 Performance Metrics

- Initial load: < 2 seconds ✅
- API responses: < 300ms ✅
- Touch response: < 100ms ✅
- Offline capable: 8+ hours ✅

## 📖 Documentation

Comprehensive documentation is available in the `/docs` folder:
- `PROJECT_CONTEXT.md` - Business and technical overview
- `ARCHITECTURE_DECISIONS.md` - Key design decisions
- `ISSUES_TRACKER.md` - Issue tracking and resolutions
- `DATABASE_REQUIREMENTS.md` - Database schema details
- `CLAUDE.md` - Development guidelines

## 🔒 Security

- Input validation on all forms
- Prepared statements for database queries
- Session management with timeout
- Action logging for audit trail
- No sensitive data in localStorage

## 🌐 Browser Support

- Chrome/Edge (Recommended)
- Safari on iPad
- Firefox
- Any modern browser with Service Worker support

## 🤝 Contributing

When making changes:
- Maintain simplicity - no complex frameworks
- Test on actual tablets
- Consider offline scenarios
- Ensure touch targets meet 44px minimum
- Follow existing patterns

## 📝 License

Internal use only - Property of Pushkar Technocast Pvt Ltd

## 🙏 Acknowledgments

Built with the philosophy that shop floor software should be as simple as using a smartphone.