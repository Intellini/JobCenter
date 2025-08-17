# Production Tracking & Visualization Design

## Overview
Enhanced visualization system for real-time production tracking with burn rate monitoring and time loss indicators.

## Core Concept: Visual Time Loss Cards

Instead of adding another column, we insert visual indicator cards between jobs that show where time was lost:

```
[Job 1] → [⚠️ Setup Delay] → [Job 2] → [🔧 Breakdown] → [Job 3]
```

## Time Loss Categories

### 1. Setup Time Loss Card
- **Color**: Orange (#FFA500)
- **Icon**: ⚙️
- **Trigger**: Setup took longer than planned
- **Display**: "+15 min setup delay"
- **Height**: Same as changeover blocks (30px)

### 2. Breakdown Loss Card  
- **Color**: Red (#DC2626)
- **Icon**: 🔧
- **Trigger**: Machine breakdown recorded
- **Display**: "45 min breakdown"
- **Height**: Proportional to time lost

### 3. Changeover Excess Card
- **Color**: Yellow (#FCD34D) 
- **Icon**: 🔄
- **Trigger**: Changeover exceeded standard time
- **Display**: "+10 min changeover"
- **Height**: Extra time beyond standard

### 4. Production Rate Loss Card
- **Color**: Purple (#9333EA)
- **Icon**: 📉
- **Trigger**: Actual production rate < planned rate
- **Display**: "Running at 75% speed"
- **Height**: Reflects accumulated delay

## Visual Implementation

### Planning Page Layout
```
┌─────────────────────────────────────────────────┐
│ Available Jobs         │  Sequenced Jobs          │
│                       │                          │
│ [Job Card]            │  [Job 1] ✓ On Track     │
│ [Job Card]            │  ↓ [+5 min setup]       │
│ [Job Card]            │  [Job 2] ⚠️ Delayed     │
│                       │  ↓ [30 min breakdown]   │
│                       │  [Job 3] 🔥 Behind      │
│                       │                          │
│                       │  Buffer: 15% → 5% ⚠️    │
└─────────────────────────────────────────────────┘
```

### Timeline Page Enhancement
```
08:00 ─────────────────────────────────── 16:00
│
├─[Job 1: GEAR-X]────────┤
│                         ├[⚙️+10]┤
│                                 ├─[Job 2: SHAFT-Y]───┤
│                                 │                     ├[🔧45]┤
│                                                              ├─[Job 3]─
```

## Burn Rate Visualization

### Dual Progress Bar System
```
Planned:  [████████████░░░░░░░] 75% (6 hrs / 8 hrs)
Actual:   [██████░░░░░░░░░░░░░] 35% (280 pcs / 800 pcs)
          └─ Burn Rate: 0.47x (Behind Schedule)
```

### Color Coding
- **Green** (>0.95x): On track
- **Yellow** (0.80-0.95x): Slightly behind
- **Orange** (0.60-0.80x): Significantly behind  
- **Red** (<0.60x): Critical delay

## Smart Buffer Time Management

### Visual Buffer Indicator
```
Buffer Time: [███░░░░░░░] 30% remaining (27 min / 90 min)
            └─ Projected: Will need 45 min overtime ⚠️
```

### Automatic Recommendations
- "Consider moving Job #5 to next shift"
- "30 min overtime needed to complete schedule"
- "Buffer exhausted - prioritize critical jobs"

## Implementation Strategy

### Phase 1: Time Loss Cards (planning.js)
```javascript
class TimeLossCard {
  constructor(type, duration, reason) {
    this.type = type; // setup|breakdown|changeover|rate
    this.duration = duration;
    this.reason = reason;
    this.color = this.getColor();
    this.icon = this.getIcon();
  }
  
  render() {
    return `
      <div class="time-loss-card ${this.type}">
        <span class="icon">${this.icon}</span>
        <span class="duration">+${this.duration} min</span>
        <span class="reason">${this.reason}</span>
      </div>
    `;
  }
}
```

### Phase 2: Burn Rate Calculator
```javascript
class BurnRateTracker {
  calculate(job) {
    const timePassed = (now - job.startTime) / job.plannedDuration;
    const qtyComplete = job.actualQty / job.plannedQty;
    return {
      burnRate: qtyComplete / timePassed,
      projection: this.projectCompletion(job),
      status: this.getStatus(qtyComplete / timePassed)
    };
  }
}
```

### Phase 3: Buffer Management
```javascript
class BufferManager {
  constructor(shiftDuration) {
    this.totalBuffer = shiftDuration * 0.15; // 15% pocket time
    this.usedBuffer = 0;
  }
  
  consumeBuffer(delay) {
    this.usedBuffer += delay;
    return {
      remaining: this.totalBuffer - this.usedBuffer,
      percentage: (this.totalBuffer - this.usedBuffer) / this.totalBuffer,
      needsOvertime: this.usedBuffer > this.totalBuffer
    };
  }
}
```

## CSS Styling

```css
/* Time Loss Cards */
.time-loss-card {
  height: 30px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  margin: 5px 0;
  font-size: 12px;
  font-weight: bold;
}

.time-loss-card.setup {
  background: linear-gradient(45deg, #FFA500, #FFB52E);
  color: white;
}

.time-loss-card.breakdown {
  background: linear-gradient(45deg, #DC2626, #EF4444);
  color: white;
  animation: pulse 2s infinite;
}

.time-loss-card.changeover {
  background: linear-gradient(45deg, #FCD34D, #FDE68A);
  color: #92400E;
}

.time-loss-card.rate {
  background: linear-gradient(45deg, #9333EA, #A855F7);
  color: white;
}

/* Burn Rate Indicators */
.burn-rate-display {
  position: relative;
  margin: 10px 0;
}

.burn-rate-bar {
  height: 20px;
  background: #E5E7EB;
  border-radius: 10px;
  overflow: hidden;
}

.burn-rate-fill {
  height: 100%;
  transition: width 0.3s ease;
}

.burn-rate-fill.on-track { background: #10B981; }
.burn-rate-fill.behind { background: #F59E0B; }
.burn-rate-fill.critical { background: #EF4444; }

/* Buffer Indicator */
.buffer-indicator {
  background: #F3F4F6;
  padding: 10px;
  border-radius: 8px;
  border-left: 4px solid;
}

.buffer-indicator.healthy { border-color: #10B981; }
.buffer-indicator.warning { border-color: #F59E0B; }
.buffer-indicator.critical { 
  border-color: #EF4444;
  animation: flash 1s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

@keyframes flash {
  0%, 100% { background: #F3F4F6; }
  50% { background: #FEE2E2; }
}
```

## Database Schema Updates

```sql
-- Track time loss events
CREATE TABLE time_loss_events (
  tle_id INT PRIMARY KEY AUTO_INCREMENT,
  tle_planning_id INT NOT NULL,
  tle_type ENUM('setup', 'breakdown', 'changeover', 'rate'),
  tle_duration INT, -- minutes
  tle_reason TEXT,
  tle_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_planning (tle_planning_id)
);

-- Track burn rate snapshots
CREATE TABLE burn_rate_tracking (
  brt_id INT PRIMARY KEY AUTO_INCREMENT,
  brt_planning_id INT NOT NULL,
  brt_time_progress DECIMAL(5,2), -- percentage
  brt_qty_progress DECIMAL(5,2), -- percentage
  brt_burn_rate DECIMAL(4,2), -- multiplier
  brt_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_planning_time (brt_planning_id, brt_timestamp)
);
```

## API Endpoints

### Record Time Loss
```php
// POST /api/time-loss
{
  "planning_id": 123,
  "type": "breakdown",
  "duration": 45,
  "reason": "Spindle motor failure"
}
```

### Get Burn Rate
```php
// GET /api/burn-rate/{planning_id}
{
  "burn_rate": 0.75,
  "time_progress": 50,
  "qty_progress": 37.5,
  "status": "behind",
  "projected_overtime": 30
}
```

### Buffer Status
```php
// GET /api/buffer-status/{machine}/{shift}
{
  "total_buffer": 72, // minutes
  "used_buffer": 65,
  "remaining": 7,
  "percentage": 9.7,
  "recommendations": [
    "Consider overtime planning",
    "Prioritize Job #3 and #4"
  ]
}
```

## Supervisor Decision Support

### Real-time Alerts
1. **Buffer < 25%**: "Buffer running low - review remaining jobs"
2. **Burn Rate < 0.8x**: "Job falling behind - check for issues"
3. **Breakdown > 15 min**: "Extended breakdown - consider rescheduling"

### Smart Suggestions
- "Move low-priority jobs to next shift"
- "Request operator from adjacent machine"
- "Switch to alternate routing for this part"
- "Contact maintenance for recurring issue"

## Benefits

1. **Visual Clarity**: See exactly where time was lost
2. **Proactive Planning**: Anticipate overtime needs
3. **Data-Driven**: Quantified delays for improvement
4. **Supervisor Empowerment**: Clear decision support
5. **Operator Awareness**: Everyone sees the impact

## Mobile/Tablet Optimization

- Touch-friendly time loss cards (44px min height)
- Swipe to acknowledge delays
- Pinch to zoom timeline view
- Shake to refresh burn rate

## Performance Considerations

- Update burn rate every 60 seconds
- Cache time loss cards for 5 minutes
- Lazy load historical data
- Use CSS animations sparingly

## Future Enhancements

1. **Predictive Analytics**: ML-based delay prediction
2. **Pattern Recognition**: Identify recurring issues
3. **Shift Handover**: Auto-generate shift reports
4. **Integration**: Connect to maintenance system
5. **Gamification**: Team performance scoring

This design provides clever visualization without adding columns, giving supervisors immediate visual feedback on delays and helping maintain the 15% buffer time effectively.