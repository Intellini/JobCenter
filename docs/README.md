# Job Center - Simplified Interface

A zero-training tablet interface for manufacturing shop floor operators.

## Overview

This is a complete rewrite of the Job Center system, designed with simplicity as the primary goal. Operators can log all their actions related to a job with simple touch interactions, no training required.

## Quick Start

1. Access the interface: `http://[server]/jc/?p=[planning_id]`
2. Select operator (if not set)
3. Start working!

## Features

- **Single Screen Interface** - Everything visible at once
- **Large Touch Targets** - Designed for gloved hands
- **Offline Capable** - Works without network connection
- **Visual Progress** - See job status at a glance
- **Bilingual** - Hindi/English labels
- **Zero Training** - Interface is self-explanatory

## Technical Details

- **No Framework** - Vanilla JavaScript and CSS
- **No Build Process** - Just PHP files
- **Progressive Web App** - Installable on tablets
- **MySQL Database** - Using existing `ptpl` schema

## Project Structure

```
/jc/
├── index.php              # Main entry point
├── config/               # Configuration files
├── api/                  # REST API endpoints
├── assets/               # CSS, JS, images
├── views/                # UI templates
└── docs/                 # Documentation
```

## Database Tables

- `mach_planning` - Job planning data
- `machine` - Machine information
- `job_actions` - Operator action log

## Development

### Requirements
- PHP 7.4+
- MySQL/MariaDB
- Modern browser with ES6 support

### Setup
1. Clone to web root
2. Configure database connection
3. Access via browser

### Testing
- Test on actual tablets
- Verify offline functionality
- Check touch responsiveness

## API Endpoints

- `GET /api/job/{id}` - Get job details
- `POST /api/action` - Log operator action
- `GET /api/status/{id}` - Get job status
- `POST /api/quantity` - Update quantity

## Design Principles

1. **Simplicity First** - If it needs explanation, it's too complex
2. **Touch Optimized** - Minimum 44px touch targets
3. **Visual Feedback** - Clear status indicators
4. **Offline First** - Queue actions when offline
5. **Fast** - Under 2 second load time

## Browser Support

- Chrome/Edge (Recommended)
- Safari on iPad
- Firefox
- Any modern browser with Service Worker support

## Contributing

When making changes:
- Maintain simplicity
- Test on tablets
- Consider offline scenarios
- Keep accessibility in mind

## License

Internal use only.