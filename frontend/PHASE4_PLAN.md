# 📋 Phase 4 - Pages Utilisateur (User-Facing Pages)

**Status:** 📋 PLANNING  
**Estimated Duration:** 2-3 hours  
**Priority:** Lower (user-facing, not admin)  

---

## 🎯 Pages à Refactoriser

### 1. MesCoursPage (Professor Dashboard)
**Current:** Basic list of courses with inline resource form  
**Target:** Professional card-based layout with improved styling

**Current Structure:**
- Simple h1 header
- List of CoursCard components
- RessourceForm inline in each card
- Basic CSS classes

**Proposed Improvements:**
- Professional page header (consistent with admin pages)
- Card grid layout (responsive, auto-fill)
- Enhanced CoursCard styling:
  - Better visual hierarchy
  - Type badges
  - Ressource list improvements
  - Modal for adding ressources (instead of inline form)
- Success/error messaging

**Fields to Display:**
- Titre (h3)
- Types cours (badges)
- Ressources (list with icons per type)
- Add resource button/modal

**Complexity:** ⭐ Low  
- No complex data structures
- No N-N relationships
- Display-focused (simple read operations)
- Ressource management is secondary

---

### 2. TimesheetsPage (Timesheet Management)
**Current:** Table layout with conditional actions based on role  
**Target:** Modern layout with better styling and optional card view

**Current Structure:**
- Simple h1 header
- Role-based view (staff vs non-staff)
- Table with rows for timesheets
- Inline actions (Soumettre, Valider)
- NewTimesheetForm for adding entries

**Proposed Approach:**
Option A: Keep table layout but modernize styling
Option B: Convert to card-based grid (less natural for tabular data)

**Recommendation:** Option A - Keep table for readability, but modernize:
- Professional header with status badge
- Styled table with better spacing/typography
- Professional buttons for actions
- Optional modal for detailed view (if needed)

**Fields to Display:**
- Date
- Heures
- Statut (with color badges)
- Actions (conditional on role/statut)

**Complexity:** ⭐ Low  
- Table layout is standard
- Role-based conditional rendering
- Simple CRUD (submit, validate)
- No nested data

---

## 🎨 Design Approach

### MesCoursPage
```
Page Header (non-sticky, user-facing)
  ├─ No admin icon
  ├─ Title "Mes cours"
  ├─ Subtitle "Retrouvez ici tous vos cours assignés"
  └─ (No action button - user facing)

Page Content
  ├─ Message if no courses
  └─ Card Grid (same as admin pages)
      ├─ CoursCard 1
      │  ├─ Titre (h3)
      │  ├─ Subtitle (types)
      │  ├─ Ressources list (styled)
      │  └─ "Ajouter ressource" button
      └─ CoursCard N

Modal (for adding ressource)
  ├─ Title, URL, Type
  └─ Submit button
```

### TimesheetsPage
```
Page Header (non-sticky)
  ├─ Title "Timesheets"
  ├─ Subtitle (role-based)
  └─ (Optional: add button for non-staff)

Page Content
  ├─ NewTimesheetForm (if non-staff)
  └─ Styled Table
      ├─ Headers (conditional on role)
      ├─ Rows with better styling
      └─ Actions (conditional buttons)
```

---

## 📊 Specifications

### MesCoursPage
| Field | Type | Display |
|-------|------|---------|
| titre | text | Card header (h3) |
| types_cours | array | Badges with colors |
| ressources | array | List with icons |
| type_ressource | enum | Type-specific icons/colors |
| url_ressource | url | Clickable link (target="_blank") |

**Actions:**
- Add ressource (modal form)
- View ressource (link)

**Styling:**
- Use AdminCardGrid for consistency
- AdminCard for each cours
- Badges for types
- Modal for ressource form

---

### TimesheetsPage
| Field | Type | Display |
|-------|------|---------|
| date_prestation | date | Table cell |
| nombre_heures | number | Table cell |
| statut_validation | enum | Color badge |
| professeur | object | Table cell (if staff) |

**Actions:**
- Soumettre (if brouillon, non-staff)
- Valider (if soumis, staff)
- View details (optional modal)

**Styling:**
- Professional table header
- Better spacing/padding
- Color-coded status badges
- Professional buttons

---

## 🛠️ Implementation Approach

### For MesCoursPage

**Files to Modify:**
- `src/pages/MesCoursPage.jsx` - main refactor

**Changes:**
1. Import admin components (AdminPageHeader, AdminCardGrid, etc)
2. Create enhanced CoursCard using AdminCard
3. Add modal for ressource form
4. Apply AdminDesignSystem colors/spacing
5. Update page header to match style (but remove action button)

**Considerations:**
- Keep RessourceForm component but move it to modal
- Reuse AdminCheckboxGroup if needed
- Use AdminBadge for type indicators
- Keep existing API calls unchanged

**Code estimate:** 150-200 lines

---

### For TimesheetsPage

**Files to Modify:**
- `src/pages/TimesheetsPage.jsx` - main refactor

**Changes:**
1. Import admin components
2. Create styled table wrapper (AdminTable or custom)
3. Apply AdminDesignSystem colors/spacing
4. Create professional buttons for actions
5. Update page header

**Considerations:**
- Keep table structure (better for tabular data)
- Style headers and cells professionally
- Use AdminBadge for status indicators
- Keep role-based conditional rendering
- Consider modal for detailed view (optional)

**Code estimate:** 150-200 lines

---

## 📋 Implementation Checklist

### Setup
- [ ] Review both pages in browser
- [ ] Identify styling opportunities
- [ ] Plan component usage

### MesCoursPage
- [ ] Refactor imports (add admin components)
- [ ] Create enhanced CoursCard using AdminCard
- [ ] Create ressource modal
- [ ] Move ressource form to modal
- [ ] Apply AdminDesignSystem styling
- [ ] Update page header
- [ ] Test in browser
- [ ] Add badges for types
- [ ] Verify responsive layout

### TimesheetsPage
- [ ] Refactor imports
- [ ] Create styled table (or use AdminTable)
- [ ] Apply professional styling to headers
- [ ] Create color-coded status badges
- [ ] Create professional action buttons
- [ ] Update page header
- [ ] Test in browser
- [ ] Verify role-based rendering
- [ ] Check responsive layout

### Final
- [ ] Browser test both pages
- [ ] Verify consistency with admin pages
- [ ] Check responsive design
- [ ] Test all user workflows
- [ ] Create completion documentation

---

## 🎨 Design System Reuse

### Colors to Use
- Primary blue (#2563eb) for main actions
- Statut badges:
  - Brouillon/Draft: amber (#f59e0b)
  - Soumis/Submitted: blue (#2563eb)
  - Validé/Validated: green (#10b981)

### Typography
- Consistent with admin pages
- Same font sizes and weights
- Professional hierarchy

### Components
- AdminPageHeader (modified - no icon for user pages)
- AdminCardGrid (for MesCoursPage)
- AdminCard (for CoursCard)
- AdminBadge (for statuses/types)
- AdminButton (for actions)
- AdminModal (for forms)

---

## ⏱️ Timeline

| Step | Duration | Status |
|------|----------|--------|
| Review & Plan | 15 min | ⏳ TODO |
| MesCoursPage | 45-60 min | ⏳ TODO |
| TimesheetsPage | 45-60 min | ⏳ TODO |
| Testing & QA | 30 min | ⏳ TODO |
| **Total** | **2-2.5 hours** | ⏳ TODO |

---

## ✨ Expected Outcomes

### MesCoursPage
✅ Professional, modern appearance  
✅ Card-based layout (consistent with admin)  
✅ Modal for ressource management  
✅ Type badges for visual organization  
✅ Responsive grid layout  

### TimesheetsPage
✅ Professional table styling  
✅ Color-coded status badges  
✅ Modern action buttons  
✅ Consistent design with admin pages  
✅ Responsive layout  

### Overall
✅ Unified design across all pages (admin + user-facing)  
✅ Professional appearance throughout app  
✅ Consistent component reuse  
✅ Better user experience  

---

## 📝 Notes

### Considerations
- User-facing pages should feel less "admin-y" - maybe simpler styling
- But consistency is important - use same design system
- Keep existing API calls unchanged
- Maintain existing functionality
- No breaking changes

### Potential Enhancements
- Loading skeletons for better perceived performance
- Animations for smooth transitions
- Keyboard shortcuts (e.g., Esc to close modal)
- Accessibility improvements (ARIA labels, etc)

### NOT Doing
- Changing API structure
- Adding new features
- Changing business logic
- Adding new validation rules
- Database migrations

---

## 🎯 Success Criteria

✅ Both pages load without errors  
✅ Styling is consistent with admin pages  
✅ All functionality works as before  
✅ Responsive layout on mobile/tablet/desktop  
✅ Professional appearance  
✅ No breaking changes  

---

**Status:** Ready to start Phase 4

