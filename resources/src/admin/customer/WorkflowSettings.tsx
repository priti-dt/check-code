import React, { useState, useEffect } from 'react';
import {
  Grid,
  Typography,
  Select,
  MenuItem,
  Autocomplete,
  Box,
} from '@mui/material';
import axios from "axios";
import CustomFormLabel from '@src/components/forms/theme-elements/CustomFormLabel';
import CustomTextField from '@src/components/forms/theme-elements/CustomTextField';
import top100Films from '@src/components/forms/form-elements/autoComplete/data';
 
const WorkflowSettings = ({ selectedValues, handleValueChange, selectedValue, selectedValueSecondary, selectedValueEscalation,primaryCrmError,secondaryCrmError,escalationError }) => {
  // Initialize selectedValue with null
  const [primaryselectedValue, setprimarySelectedValue] = useState(null);
  const [secondaryselectedValue, setsecondarySelectedValue] = useState(null);
  const [escalationselectedValue, setescalationselectedValue] = useState(null);
  const [employeeData, setEmplyeeData] = useState([]);
  const appUrl = import.meta.env.VITE_API_URL;
  

  // Use useEffect to set the selectedValue based on the provided id
  useEffect(() => {
      employeeList();
      const primaryeditItemId = parseInt(selectedValues.primaryCrm);
      const secondaryeditItemId = parseInt(selectedValues.secondaryCrm);
      const escalationeditItemId = parseInt(selectedValues.escalation);
      //Value For Primary CRM
      const primarymatchedItem = employeeData.find(item => item.id === primaryeditItemId);
      if (primarymatchedItem) {
        setprimarySelectedValue(primarymatchedItem);
      } else {
        console.log("No match found in employeeData for editItemId:", primaryeditItemId);
        setprimarySelectedValue(null);
      }
      //Value For Secondary CRM
      const secondarymatchedItem = employeeData.find(item => item.id === secondaryeditItemId);
      if (secondarymatchedItem) {
        setsecondarySelectedValue(secondarymatchedItem);
      } else {
        console.log("No match found in employeeData for editItemId:", secondaryeditItemId);
        setsecondarySelectedValue(null);
      }
      //Value For Escalation
      const escalationmatchedItem = employeeData.find(item => item.id === escalationeditItemId);
      if (escalationmatchedItem) {
        setescalationselectedValue(escalationmatchedItem);
      } else {
        console.log("No match found in employeeData for editItemId:", escalationeditItemId);
        setescalationselectedValue(null);
      }
  }, [selectedValues.primaryCrm,selectedValues.secondaryCrm,selectedValues.escalation]);
  const primaryCrmValue = selectedValue ? selectedValue : primaryselectedValue;
  const secondaryCrmValue = selectedValueSecondary ? selectedValueSecondary : secondaryselectedValue;
  const escalationValue = selectedValueEscalation ? selectedValueEscalation : escalationselectedValue;

  const employeeList = async () => {
    try {
      const formData = new FormData();
      formData.append('sortBy', 'id');
      formData.append('sortOrder', 'asc');
      formData.append('isActive', '1');

      const API_URL = appUrl + '/api/list-employees';
      const token = sessionStorage.getItem('authToken');
      const response = await axios.post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setEmplyeeData(response.data.data.data);
    } catch (error) {
      //console.error("Error:", error);
    }
  };
  return (
    <Grid container spacing={2}>
      <Grid item xs={12}>
        <Typography variant="h5">Set Workflow</Typography>
      </Grid>
      <Grid item xs={12} sm={6} md={4}>
      <Grid item xs={12} display={'flex'}>
        <CustomFormLabel htmlFor="primary_crm_user_id">Primary CRM</CustomFormLabel>
        <Typography
            variant="h5"
            mt={3}
            sx={{
                color: (theme) =>
                    theme.palette.error.main,
                marginLeft: "5px",
            }}
        >
            {" "}
            *
        </Typography>
      </Grid>
        <Autocomplete
        id="primary_crm_user_id"
        fullWidth
        options={employeeData}
        autoHighlight
        getOptionLabel={(option) => option.name}
        renderOption={(props, option) => (
          <MenuItem
            component="li"
            {...props}
          >
            {option.name}
          </MenuItem>
        )}
        value={primaryCrmValue} // Set the selected value here
        onChange={(event, newValue) => handleValueChange(event, newValue, 'primaryCrm')} // Pass the field name
        renderInput={(params) => (
          <CustomTextField
            {...params}
            placeholder="Primary CRM"
            aria-label="Primary CRM"
            autoComplete="off"
            inputProps={{
              ...params.inputProps,
              autoComplete: 'new-password', // disable autocomplete and autofill
            }}
            style={{ padding: '1px' }}
          />
        )}
      />
      {primaryCrmError && (
        <Typography variant="body2" color="error">
          {primaryCrmError}
        </Typography>
      )}
      </Grid>

      <Grid item xs={12} sm={6} md={4}>
      <Grid item xs={12} display={'flex'}>
        <CustomFormLabel htmlFor="secondary_crm_user_id">Secondary CRM</CustomFormLabel>
        <Typography
                                    variant="h5"
                                    mt={3}
                                    sx={{
                                        color: (theme) =>
                                            theme.palette.error.main,
                                        marginLeft: "5px",
                                    }}
                                >
                                    {" "}
                                    *
                                </Typography>
                                </Grid>
        <Autocomplete
        id="secondary_crm_user_id"
        fullWidth
        options={employeeData}
        autoHighlight
        getOptionLabel={(option) => option.name}
        renderOption={(props, option) => (
          <MenuItem
            component="li"
            {...props}
          >
            {option.name}
          </MenuItem>
        )}
        value={secondaryCrmValue} // Set the selected value here
        onChange={(event, newValue) => handleValueChange(event, newValue, 'secondaryCrm')} // Pass the field name
        renderInput={(params) => (
          <CustomTextField
            {...params}
            placeholder="Secondary CRM"
            aria-label="Secondary CRM"
            autoComplete="off"
            inputProps={{
              ...params.inputProps,
              autoComplete: 'new-password', // disable autocomplete and autofill
            }}
            style={{ padding: '1px' }}
          />
        )}
      />
      {secondaryCrmError && (
        <Typography variant="body2" color="error">
          {secondaryCrmError}
        </Typography>
      )}
      </Grid>

      <Grid item xs={12} sm={6} md={4}>
      <Grid item xs={12} display={'flex'}>
        <CustomFormLabel htmlFor="escalation_user_id">Escalation</CustomFormLabel>
        <Typography
                                    variant="h5"
                                    mt={3}
                                    sx={{
                                        color: (theme) =>
                                            theme.palette.error.main,
                                        marginLeft: "5px",
                                    }}
                                >
                                    {" "}
                                    *
                                </Typography>
      </Grid>
        <Autocomplete
        id="escalation_user_id"
        fullWidth
        options={employeeData}
        autoHighlight
        getOptionLabel={(option) => option.name}
        renderOption={(props, option) => (
          <MenuItem
            component="li"
            {...props}
          >
            {option.name}
          </MenuItem>
        )}
        value={escalationValue} // Set the selected value here
        onChange={(event, newValue) => handleValueChange(event, newValue, 'escalation')} // Pass the field name
        renderInput={(params) => (
          <CustomTextField
            {...params}
            placeholder="Escalation"
            aria-label="Escalation"
            autoComplete="off"
            inputProps={{
              ...params.inputProps,
              autoComplete: 'new-password', // disable autocomplete and autofill
            }}
            style={{ padding: '1px' }}
          />
        )}
      />
      {escalationError && (
        <Typography variant="body2" color="error">
          {escalationError}
        </Typography>
      )}
      </Grid>
    </Grid>
  );
};

export default WorkflowSettings;