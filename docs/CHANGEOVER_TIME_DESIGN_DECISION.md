# Changeover Time Display Design Decision

## Date: 2025-08-15

## Context
The planning interface needed a way to display and edit changeover times between jobs in the production sequence. GitHub issues #7 and #10 described problems with the original orange circle implementation.

## Original Design (Problems)
- Orange circles with changeover time between jobs
- Circles positioned too high, not clearly connected to job pairs
- Circles were not clickable/editable
- Visual confusion about which jobs the time applied to

## New Design Decision: Small Horizontal Cards

### Implementation
- **Small cards (28px height)** positioned between job cards
- **Horizontal layout** with gradient orange background
- **Editable input field** directly in the card
- **Clear visual connection** to the jobs above and below

### Benefits
1. **Better visual clarity** - Cards span the width, clearly separating jobs
2. **Easier to click** - Larger target area than circles
3. **Inline editing** - Direct input field, no modal needed
4. **Responsive** - Cards scale well on different screen sizes
5. **Intuitive** - Users immediately understand the time applies between the two jobs

### Technical Details
- Height: 28px (24px on tablets, 22px on mobile)
- Width: Full width minus padding
- Background: Linear gradient orange (#ff6b35 to #f97316)
- Input: White background with orange text for contrast
- Storage: Individual times stored in localStorage as `changeover_times` object

### User Interaction
1. Click on the input field in any changeover card
2. Type new time value (0-999 minutes)
3. Press Enter or click outside to save
4. Timeline automatically recalculates
5. Total changeover time updates in summary

### Data Structure
```javascript
// localStorage structure
changeover_times: {
  "0": 15,  // Between job 1 and 2
  "1": 20,  // Between job 2 and 3
  "2": 15   // Between job 3 and 4
}
```

## Rationale
Small horizontal cards provide better usability than circles:
- More accessible for touch interfaces
- Clearer visual hierarchy
- Easier to implement and maintain
- Better alignment with material design principles
- Solves all issues mentioned in GitHub #7 and #10

## Future Considerations
- Could add preset buttons (5, 10, 15, 20, 30 min)
- Could color-code based on changeover complexity
- Could add tooltips with changeover reason/notes