import React, { useState } from 'react';
import {
  TextField,
  Menu,
  MenuItem,
  Checkbox,
  ListItemText,
  Grid,
} from '@mui/material';

const CustomSelect = ({ options, value, onChange }) => {
  const [anchorEl, setAnchorEl] = useState(null);

  const handleOpenMenu = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const handleCloseMenu = () => {
    setAnchorEl(null);
  };

  const handleMenuItemClick = (item) => () => {
    if (value.includes(item)) {
      onChange(value.filter((v) => v !== item));
    } else {
      onChange([...value, item]);
    }
  };

  // Helper function to get template names based on selected IDs
  const getSelectedTemplateNames = () => {
    return options
      .filter((item) => value.includes(item.id))
      .map((item) => item.template_name);
  };

  return (
    <Grid item xs={12} sm={6} md={4}>
      <TextField
        id="custom-select"
        label="Select Terms & Conditions"
        onClick={handleOpenMenu}
        value={value.length > 0 ? getSelectedTemplateNames().join(', ') : ''}
        variant="outlined"
        fullWidth
        size="small"
        style={{ minWidth: '500px' }}
        InputProps={{
          readOnly: true,
        }}
      />
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={handleCloseMenu}
        multiple
      >
        {options.map((item) => (
          <MenuItem key={item.id} onClick={handleMenuItemClick(item.id)}>
            <Checkbox checked={value.includes(item.id)} />
            <ListItemText primary={item.template_name} />
          </MenuItem>
        ))}
      </Menu>
    </Grid>
  );
};

export default CustomSelect;