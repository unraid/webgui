# Dynamix Theme System Documentation

## Overview

The Dynamix theme system uses CSS variables (custom properties) to create a flexible and maintainable theming solution. The system is built on several layers that work together to provide consistent styling across the webGUI.

## Core Components

### 1. Base Color Palette (`default-color-palette.css`)

- Defines fundamental color variables used throughout the system
- Contains standardized color scales (e.g., `--gray-100` through `--gray-900`)
- Includes opacity variants for common colors (e.g., `--white-opacity-10`)
- Ideally should not be overridden by theme files but instead just used in theme files

### 2. Base Styles (`default-base.css`)

- Provides foundational styling for the entire application
- Defines core layout and component styles
- Implements responsive design patterns
- Key sections include:
  - Typography and text styles
  - Form elements (inputs, buttons, selects)
  - Layout components (header, menu, footer)
  - Grid and table structures
  - Utility classes and animations
  - Theme-specific modifiers (e.g., `.Theme--sidebar`)

#### Base Style Categories

1. **Core Elements**
   - HTML/Body defaults
   - Typography scales
   - Link styles
   - Form elements

2. **Layout Components**
   - Header structure
   - Navigation system
   - Content areas
   - Footer positioning

3. **Interactive Elements**
   - Button states
   - Form input behaviors
   - Hover effects
   - Focus states

4. **Utility Classes**
   - Color modifiers (`.green`, `.red`, etc.)
   - Status indicators
   - Usage bars and meters
   - Helper classes

5. **Responsive Patterns**
   - Media queries for different screen sizes
   - Flexible layouts
   - Mobile adaptations

### 3. Theme Files (`themes/*.css`)

- Located in `emhttp/plugins/dynamix/styles/themes/`
- Each theme (azure, black, gray, white) defines its specific color scheme
- Uses variables from the base color palette
- Sets theme-specific variables used by component sheets

### 4. Component Sheets (`sheets/*.css`)

Component sheets are located in `emhttp/plugins/dynamix/styles/sheets/` and follow these patterns:

#### Variable Declaration

```css
:root {
    --component-element-property-color: var(--color-palette-variable);
}
```

#### Theme-Specific Overrides

```css
.Theme--{themename}:root {
    --component-element-property-color: var(--theme-specific-variable);
}
```

## Component Sheet Types

### 1. Feature-Specific Sheets

Files like `Browse.css`, `DashStats.css` that define styles for specific features:

- Declare component-specific variables
- Use theme variables for consistent styling
- Include theme-specific overrides when needed

### 2. Utility Sheets

Files like `UserList.css`, `ShareList.css` that provide reusable styles:

- Focus on layout and structure
- Minimal use of theme-specific variables
- Primarily use base variables

## Naming Conventions

### CSS Variables

- Component variables: `--component-element-property`

- Theme-specific variables: `--theme-{name}-color-{color}-{shade}`
- Component state variables: `--component-state-{state}-{property}`

### Theme Classes

- Main theme: `.Theme--{themename}`
- Layout variants: `.Theme--sidebar`, `.Theme--nav-top`

## Best Practices

1. **Variable Usage**
   - Always use color palette variables instead of hardcoded colors
   - Create component-specific variables for reusable values
   - Use semantic naming for component variables

2. **Theme Overrides**
   - Keep theme-specific overrides in theme classes
   - Use the most specific selector needed
   - Document any special theme behavior

3. **Component Organization**
   - Group related styles together
   - Use comments to separate major sections
   - Keep component-specific variables at the top of the file

4. **Responsive Design**
   - Use relative units where possible
   - Consider theme impacts on responsive layouts
   - Test across different themes and layouts

## Example Usage

```css
/* Component variable declaration */
:root {
    --component-bg-color: var(--gray-100);
    --component-text-color: var(--black);
}

/* Theme-specific override */
.Theme--dark:root {
    --component-bg-color: var(--gray-900);
    --component-text-color: var(--white);
}

/* Component styles using variables */
.component {
    background-color: var(--component-bg-color);
    color: var(--component-text-color);
}
```

## File Organization

The sheets directory contains:

- Feature-specific styles (e.g., `Browse.css`, `DashStats.css`)
- Utility styles (e.g., `UserList.css`, `ShareList.css`)

Each file should focus on a specific component or feature and use the theming system consistently.

## Anti-Patterns to Avoid

### Theme Variant Files ⛔

Creating separate theme variant files (e.g., `Eth0-azure.css`, `Wireless-black.css`) is strongly discouraged as it:

- Creates unnecessary code duplication
- Makes maintenance more difficult
- Increases the chance of inconsistencies between themes
- Makes theme-wide changes require updates to multiple files
- Violates DRY (Don't Repeat Yourself) principles

Instead, use CSS nesting with theme classes and CSS variables:

```css
/* ❌ Don't do this - creating separate theme files */
/* Eth0-azure.css */
input[type=text].narrow {
    width: 188px;
    padding: 5px 6px;
    border: 1px solid #606e7f;
    color: #606e7f;
}

/* Eth0-black.css */
input[type=text].narrow {
    width: 166px;
    padding: 4px 0;
    border: none;
    border-bottom: 1px solid #e5e5e5;
    color: #f2f2f2;
}

/* ✅ Do this instead - single file with theme variables and nesting */
/* Eth0.css */
:root {
    /* file specific variables */
    --eth0-narrow-input-width: 188px;
    --eth0-narrow-input-padding: 5px 6px;
    --eth0-narrow-input-border-color: var(--border-color);
    --eth0-narrow-input-text-color: var(--text-color);
}

/* theme file specific variables */
.Theme--black,
.Theme--white {
    --eth0-narrow-input-width: 166px;
    --eth0-narrow-input-padding: 4px 0;
}

.Theme--black {
    --eth0-narrow-input-border-color: var(--gray-100);
    --eth0-narrow-input-text-color: var(--gray-100);
}

.Theme--white {
    --eth0-narrow-input-border-color: var(--gray-900);
    --eth0-narrow-input-text-color: var(--gray-900);
}

input[type="text"].narrow {
    width: var(--eth0-narrow-input-width);
    padding: var(--eth0-narrow-input-padding);
    border: 1px solid var(--eth0-narrow-input-border-color);
    color: var(--eth0-narrow-input-text-color);
}

/*
 * Theme specific overrides - 
 * Notice that we are not repeating the all the input[type="text"].narrow styles in each theme file specific block.
 * Instead, we are using the CSS nesting to override the styles for each theme only as needed.
 * This is a much more efficient and maintainable approach. Preventing CSS duplication and reducing the chance of inconsistencies.
 */
.Theme--black,
.Theme--white {
    input[type="text"].narrow {
        border: none;
        border-bottom: 1px solid var(--eth0-narrow-input-border-color);
    }
}
```

### Benefits of the Recommended Approach

1. **Single Source of Truth**: All theme-related styles for a component are in one file
2. **Better Maintainability**: Changes only need to be made in one place
3. **Improved Readability**: Theme variations are clearly visible and grouped
4. **Easier Theme Updates**: Global theme changes can be made by updating variables
5. **Better IDE Support**: Modern IDEs can provide better autocomplete and validation
6. **Reduced File Count**: Fewer files to manage and track
7. **Consistent Patterns**: Enforces consistent theming patterns across components

### Migration Strategy

When encountering theme variant files:

1. Create component-specific variables in the main component file
2. Move theme-specific values into appropriate `.Theme--{name}` blocks
3. Use CSS nesting to override specific properties when needed
4. Delete the theme variant files once migrated
5. Update any references to the deleted files
