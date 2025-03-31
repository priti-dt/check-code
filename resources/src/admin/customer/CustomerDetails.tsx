import React, { useEffect, useState } from "react";
import Breadcrumb from '@src/layouts/full/shared/breadcrumb/Breadcrumb';
import PageContainer from '@src/components/container/PageContainer';
import ParentCard from '@src/components/shared/ParentCard';
import CustomFormLabel from '@src/components/forms/theme-elements/CustomFormLabel';
import CustomTextField from '@src/components/forms/theme-elements/CustomTextField';
import CustomCheckbox from '@src/components/forms/theme-elements/CustomCheckbox';
import DoneIcon from '@mui/icons-material/Done';
import WorkflowSettings from './WorkflowSettings';
import CustomSelect from './CustomSelect';
import axios from "axios";
import { Link, useParams } from 'react-router-dom';
import { Divider,Autocomplete } from '@mui/material';
import Snackbar from "@mui/material/Snackbar";
import { Portal } from '@mui/base';
import {
  Timeline,
  TimelineItem,
  TimelineOppositeContent,
  TimelineSeparator,
  TimelineDot,
  TimelineConnector,
  TimelineContent,
  timelineOppositeContentClasses,
} from "@mui/lab";
import {
  Grid,
  Box,
  Chip,
  Typography,
  FormControl,
  MenuItem,
  RadioGroup,
  FormControlLabel,
  Button,
  SliderValueLabelProps,
  Stack,
  Select,
  Alert,
  AlertTitle,
  Checkbox,
  ListItemText,
  CircularProgress,
} from '@mui/material';

const BCrumb = [
  {
    to: '/admin/customer-list',
    title: 'Customer List',
  },
  {
    title: 'Customer Details',
  },
];

const CustomerDetails = () => {

  const { id } = useParams(); // Get the 'id' parameter from the URL
  const [customerData, setCustomerData] = useState(null);
  const [termsData, setTermsData] = useState([]);
  const [checkedTerms, setCheckedTerms] = useState([]);
  const [error, setError] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [validationSuccess, setValidationSuccess] = useState(false);
  const [selectedValue, setSelectedValue] = useState(null);
  const [selectedValueSecondary, setSelectedValueSecondary] = useState(null);
  const [selectedValueEscalation, setselectedValueEscalation] = useState(null);

  const [primaryCrmValue, setPrimaryCrmValue] = useState(null);
  const [secondaryCrmValue, setSecondaryCrmValue] = useState(null);
  const [escalationValue, setEscalationValue] = useState(null);

  const [selectedValues, setSelectedValues] = useState({
    primaryCrm: '',
    secondaryCrm: '',
    escalation: '',
  });
  const [validationError, setValidationError] = useState(false);
  const [selectedTerms, setSelectedTerms] = useState([]);
  const [editselectedTerms, seteditSelectedTerms] = useState([]);

  const [primaryCrmError, setPrimaryCrmError] = useState('');
  const [secondaryCrmError, setSecondaryCrmError] = useState('');
  const [escalationError, setEscalationError] = useState('');
  const [isLoading, setIsLoading] = useState(false);



  const handleValueChange = (event, newValue, fieldName) => {
    // Assuming newValue is an item object with an 'id' property
    const selectedItemId = newValue ? newValue.id : null;
    console.log('newValue : ' + newValue);
    // Keep track of the previous value in a state variable
    //const previousValue = fieldName === 'primaryCrm' ? primaryCrmValue : fieldName === 'secondaryCrm' ? secondaryCrmValue : fieldName === 'escalation' ? escalationValue : null;
    
    // Update the selected field based on the provided fieldName
    // Validate individual fields
    if (fieldName === 'primaryCrm') {
      if (
        selectedItemId === secondaryCrmValue ||
        selectedItemId === escalationValue
      ) {
        setPrimaryCrmError('Primary CRM must be different from Secondary CRM & Escalation');
      } else {
        setPrimaryCrmError('');
      }
    } else if (fieldName === 'secondaryCrm') {
      if (
        selectedItemId === primaryCrmValue ||
        selectedItemId === escalationValue
      ) {
        setSecondaryCrmError('Secondary CRM must be different from Primary CRM & Escalation');
      } else {
        setSecondaryCrmError('');
      }
    } else if (fieldName === 'escalation') {
      if (
        selectedItemId === primaryCrmValue ||
        selectedItemId === secondaryCrmValue
      ) {
        setEscalationError('Escalation must be different from Primary CRM & Secondary CRM');
      } else {
        setEscalationError('');
      }
    }

    if(newValue)
    {
      if (fieldName === 'primaryCrm') {
        setPrimaryCrmValue(selectedItemId);
      } else if (fieldName === 'secondaryCrm') {
        setSecondaryCrmValue(selectedItemId);
      } else if (fieldName === 'escalation') {
        setEscalationValue(selectedItemId);
      }
    }
    else
    {
      if (fieldName === 'primaryCrm') {
        setPrimaryCrmValue(null);
      } else if (fieldName === 'secondaryCrm') {
        setSecondaryCrmValue(null);
      } else if (fieldName === 'escalation') {
        setEscalationValue(null);
      }
    }
    // Create a copy of the selectedValues object
    const updatedSelectedValues = { ...selectedValues };
  
    // Update the selected field based on the provided fieldName
    updatedSelectedValues[fieldName] = selectedItemId;
  
    // Update the state with the updated selectedValues
    setSelectedValues(updatedSelectedValues);
    //Check if the selected value is already selected in other fields
    if (
      (fieldName === 'primaryCrm' && selectedItemId === secondaryCrmValue && secondaryCrmValue != null) ||
      (fieldName === 'primaryCrm' && selectedItemId === escalationValue && escalationValue != null) ||
      (fieldName === 'secondaryCrm' && selectedItemId === primaryCrmValue && primaryCrmValue != null) ||
      (fieldName === 'secondaryCrm' && selectedItemId === escalationValue && escalationValue != null) ||
      (fieldName === 'escalation' && selectedItemId === primaryCrmValue && primaryCrmValue != null) ||
      (fieldName === 'escalation' && selectedItemId === secondaryCrmValue && secondaryCrmValue != null)
    ) {
    }
    else{
      if (fieldName === 'primaryCrm')
      {
        if((selectedItemId != secondaryCrmValue  && secondaryCrmValue != null) || (escalationValue != selectedItemId && escalationValue != null))
        {
          setPrimaryCrmError('');
        }
        if(secondaryCrmValue != escalationValue)
        {
          setSecondaryCrmError('');
          setEscalationError('');
        }
      }
      else if (fieldName === 'secondaryCrm')
      {
        if((selectedItemId != primaryCrmValue  && primaryCrmValue != null) || (escalationValue != selectedItemId && escalationValue != null))
        setSecondaryCrmError('');
        if(primaryCrmValue != escalationValue)
        {
          setPrimaryCrmError('');
          setEscalationError('');
        }
      }
      else if (fieldName === 'escalation')
      {
        if((selectedItemId != primaryCrmValue  && primaryCrmValue != null) || (selectedItemId != secondaryCrmValue  && secondaryCrmValue != null))
        setEscalationError('');
        if(secondaryCrmValue != primaryCrmValue)
        {
          setSecondaryCrmError('');
          setPrimaryCrmError('');
        }
      }
      /*else
      {
        setPrimaryCrmError('');
        setSecondaryCrmError('');
        setEscalationError('');
      }*/
    }
    // Clear the error message if no duplicate is detected
    setErrorMessage('');
    setValidationError(false);
  };

  // Function to handle changes in any Select component
  /*const handleSelectChange = (id) => (event) => {
    setSelectedValues({
      ...selectedValues,
      [id]: event.target.value,
    });
  };*/

  const appUrl = import.meta.env.VITE_API_URL;

  useEffect(() => {
    setIsLoading(true);
  }, []);

  useEffect(() => {
    //fetch or processing delay
    const timer = setTimeout(() => {
      fetchData();
    }, 1000);
    termsList();
  }, [id]);

  
  useEffect(() => {
    // Set the selected terms
    const termsConditionIds = editselectedTerms;
    if (termsConditionIds !== null) {
      const selectedOptions = termsData.filter(option => termsConditionIds.includes(option.id));
      setSelectedTerms(selectedOptions);
    }
  }, [editselectedTerms,termsData]);

  //Customer Data Fetched
  const fetchData = async () => {
    setIsLoading(true);
    const apiUrl = `${appUrl}/api/show-customer/${id}`;
    try {
      const token = sessionStorage.getItem('authToken');
      const response = await axios.get(apiUrl, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      
      setCustomerData(response.data.data);
      // Update the state with the fetched data
      setSelectedValues({
        primaryCrm: parseInt(response.data.data.customer.primary_crm_user_id) || '',
        secondaryCrm: parseInt(response.data.data.customer.secondary_crm_user_id) || '',
        escalation: parseInt(response.data.data.customer.escalation_user_id) || '',
      });

      // Update the state with the fetched data
      setPrimaryCrmValue(parseInt(response.data.data.customer.primary_crm_user_id));
      setSecondaryCrmValue(parseInt(response.data.data.customer.secondary_crm_user_id));
      setEscalationValue(parseInt(response.data.data.customer.escalation_user_id));
      if (response.data.data.customer.terms_condition_ids !== null) {
        seteditSelectedTerms(JSON.parse(response.data.data.customer.terms_condition_ids));
      }
    } catch (error) {
      //setError(error.response.data.message);
      //console.log('Error',error.response.data.message);
    }finally{
      setIsLoading(false);
    }
  };


  //Call Terms List
  const termsList = async () => {
    try {
      const formData = new FormData();
      formData.append('sortBy', 'id');
      formData.append('sortOrder', 'ASC');
      formData.append('default_spare_or_customer', '0');

      const API_URL = appUrl + '/api/list-terms';
      const token = sessionStorage.getItem('authToken');
      const response = await axios.post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setTermsData(response.data.data.data);
    } catch (error) {
      //console.error("Error:", error);
    }
  };
  
  const handleUpdateWorkflow = () => {
     //const termsConditionIds = JSON.stringify(selectedTerms);
     const termsConditionIds = JSON.stringify(selectedTerms.map(term => term.id));

     const formData = new FormData();
     formData.append('user_id', id);
     formData.append('primary_crm_user_id', selectedValues.primaryCrm);
     formData.append('secondary_crm_user_id', selectedValues.secondaryCrm);
     formData.append('escalation_user_id', selectedValues.escalation);
     formData.append('terms_condition_ids', termsConditionIds);
 
     const API_URL = `${appUrl}/api/edit-customer`;
     const token = sessionStorage.getItem('authToken');
 
     axios
       .post(API_URL, formData, {
         headers: {
           Authorization: `Bearer ${token}`,
         },
       })
       .then((response) => {
         setSuccessMessage('Workflow updated successfully!');
         setValidationSuccess(true);
         setTimeout(() => {
           setValidationSuccess(false);
         }, 5000);
         console.log('Edit customer response:', response.data);
       })
       .catch((error) => {
         setValidationError(true);
          //setErrorMessage(error.response.data.message);
          const validationErrors = error.response.data.validation_error;
          const errorMessages = [];

          // Iterate through each field in the validation_errors object
          for (const field in validationErrors) {
              if (validationErrors.hasOwnProperty(field)) {
                  // Get the error message for the field
                  const errorMessage = validationErrors[field][0];

                  // Add the error message to the array
                  errorMessages.push(errorMessage);
              }
          }
          const concatenatedErrorMessage = errorMessages.join("\n");
          setErrorMessage(concatenatedErrorMessage);
         setTimeout(() => {
           setValidationError(false);
         }, 5000);
         //setErrorMessage('Error in updating workflow!');
         console.error('Edit customer error:', error);
       });
  }
  
  const handleSendInvite = () => {
    if (
      selectedValues.primaryCrm === '' ||
      selectedValues.secondaryCrm === '' ||
      selectedValues.escalation === ''
    ) {
      setValidationError(true);
      setErrorMessage('Please set the work flow for send invite to the customer');
      return;
    }
    setValidationError(false);

    //const termsConditionIds = JSON.stringify(selectedTerms);
    const termsConditionIds = JSON.stringify(selectedTerms.map(term => term.id));

    const formData = new FormData();
    formData.append('user_id', id);
    formData.append('primary_crm_user_id', selectedValues.primaryCrm);
    formData.append('secondary_crm_user_id', selectedValues.secondaryCrm);
    formData.append('escalation_user_id', selectedValues.escalation);
    formData.append('terms_condition_ids', termsConditionIds);

    const API_URL = `${appUrl}/api/edit-customer`;
    const token = sessionStorage.getItem('authToken');

    axios
      .post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
      .then((response) => {
        setSuccessMessage('Workflow set and invite sent successfully!!!');
        handleInviteCustomer(id);
        setValidationSuccess(true);
        setTimeout(() => {
          setValidationSuccess(false);
        }, 5000);
        console.log('Edit customer response:', response.data);
      })
      .catch((error) => {
        setValidationError(true);
        setTimeout(() => {
          setValidationError(false);
        }, 5000);
        setErrorMessage('Please Select primary crm, secondary crm and escalation user ID must be different from each other');
        console.error('Edit customer error:', error);
      });
  };

  //Handle the handle Invite Customer function
  const handleInviteCustomer = async (id) => {
    try {
      const formData = new FormData();
      formData.append('id', id);

      const API_URL = appUrl + '/api/send-invite';
      const token = sessionStorage.getItem('authToken');
      const response = await axios.post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      fetchData();
      //console.log("Success", response.data);
    } catch (error) {
      //console.error("Error:", error);
    }
  };

  {/*const handleTermsChange = (selected) => {
    setSelectedTerms(selected);
  };*/}

  const handleTermsChange = (event, newValue) => {
    // Assuming newValue is an array of selected options
    setSelectedTerms(newValue);
  };
  
  return (
    <div style={{ position: "relative", minHeight: "100vh" }}>
      {/* Loader Overlay */}
      {isLoading && (
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            position: "fixed", // Changed from absolute to fixed
            top: 0,
            left: 0,
            width: "100%",
            height: "100%",
            backgroundColor: "rgba(255, 255, 255, 0.7)", // Optional semi-transparent background
            zIndex: 9999, // Ensure it overlays the page
          }}
        >
          <CircularProgress size={50} color="primary" />
        </div>      
      )}
    <PageContainer title="Customer Details" description="this is Customer Details page">
      {/* breadcrumb */}
      <Breadcrumb title="" items={BCrumb} />
      {/* end breadcrumb */} 
      <Portal>
              <Snackbar
                    anchorOrigin={{ vertical: "top", horizontal: "right" }}
                    open={validationError}
                    autoHideDuration={3000}
                    onClose={() => setValidationError(false)}
                >
                    <Alert severity="error">
                      <div style={{ fontSize: "14px", padding: "2px" }} dangerouslySetInnerHTML={{ __html: errorMessage.replace(/\n/g, '<br />') }} />
                    </Alert>
                </Snackbar>
            </Portal>
            <Portal>
            <Snackbar
                anchorOrigin={{ vertical: "top", horizontal: "right" }}
                open={validationSuccess}
                autoHideDuration={3000}
                onClose={() => setValidationSuccess(false)}
            >
                <Alert severity="success">
                    <div style={{ fontSize: "14px", padding: "2px" }}>
                        {successMessage && <div>{successMessage}</div>}
                    </div>
                </Alert>
            </Snackbar>
            </Portal>
      <ParentCard title="Customer Details">
        <Grid container spacing={1}>
          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Customer ID
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                            {customerData ? customerData.user_code : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Account Type
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData ? customerData.customer.account_type : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Account Type Details
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.account_type_details : '-'}
                        </Typography>
           
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Company Name
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData ? customerData.name : '-'}
                        </Typography>
          </Grid>

          {/* <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Company Last Name
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData && customerData.customer.customer_name2 != null ? customerData.customer.customer_name2 : '-'}
                        </Typography>
         
          </Grid> */}

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Company Email ID
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.email_id : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          GSTIN
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData ? customerData.customer.gst_no : '-'}
                        </Typography>
           
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          PAN
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.pan_no : '-'}
                        </Typography>
        
          </Grid>


          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Street
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.street : '-'}
                        </Typography>
        
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          City
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.city : '-'}
                        </Typography>
       
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Country Region
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.country_region : '-'}
                        </Typography>
        
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Region
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
{customerData ? customerData.customer.region : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Region Description
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData ? customerData.customer.region_description : '-'}
                        </Typography>
           
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Postal Code
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData ? customerData.customer.postal_code : '-'}
                        </Typography>
         
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
            <Typography variant="body2" color="text.secondary">
              Currency Dealing
            </Typography>
            <Typography
              variant="subtitle1"
              mb={0.5}
              fontWeight={600}
            >
              {customerData && customerData.customer.currency_dealing ? customerData.customer.currency_dealing : '-'}
            </Typography>
          </Grid>

          <Divider sx={{ width: '100%', my: 2 }} />
          <Grid item xs={12}>
            <Typography variant="h5">User Details</Typography>
          </Grid>
          
          <Grid item xs={12} sm={12} lg={3} my={2}>      
          <Typography variant="body2" color="text.secondary">
          Name
                            </Typography>
                            <Typography
                                variant="subtitle1"
                                mb={0.5}
                                fontWeight={600}
                            >
                              {customerData && customerData.customer.contact_person_name != null ? customerData.customer.contact_person_name : '-'}
                            </Typography>
         </Grid>

          <Grid item xs={12} sm={12} lg={2} my={2}>
          <Typography variant="body2" color="text.secondary">
          Country Code
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData && customerData.customer.contact_person_country_code && customerData.customer.contact_person_country_code != null ? ("+"+customerData.customer.contact_person_country_code) : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={2} my={2}>
          <Typography variant="body2" color="text.secondary">
          Contact Number
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData && customerData.customer.contact_person_number != null ? customerData.customer.contact_person_number : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={3} my={2}>
          <Typography variant="body2" color="text.secondary">
          Work Email
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData && customerData.customer.contact_person_work_email != null ? customerData.customer.contact_person_work_email : '-'}
                        </Typography>
          
          </Grid>

          <Grid item xs={12} sm={12} lg={2} my={2}>
          <Typography variant="body2" color="text.secondary">
          Work Location
                        </Typography>
                        <Typography
                            variant="subtitle1"
                            mb={0.5}
                            fontWeight={600}
                        >
                          {customerData && customerData.customer.contact_person_work_location != null ? customerData.customer.contact_person_work_location : '-'}
                        </Typography>
          </Grid>

          <Divider sx={{ width: '100%', my: 2 }} />

          {/* WorkflowSettings component */}
          <WorkflowSettings
            selectedValues={selectedValues}
            handleValueChange={handleValueChange}
            selectedValue={selectedValue}
            selectedValueSecondary={selectedValueSecondary}
            selectedValueEscalation={selectedValueEscalation}
            primaryCrmError={primaryCrmError}
            secondaryCrmError={secondaryCrmError}
            escalationError={escalationError}
          />

          <Grid item xs={12} mt={3} display={'flex'}>
            <Typography variant="h5">Specific Terms and Conditions</Typography>
          </Grid>
          {/* <CustomSelect
              options={termsData}
              value={selectedTerms}
              onChange={handleTermsChange}
            />*/}
         <Autocomplete
          multiple
          id="checkboxes-tags-demo"
          options={termsData}
          disableCloseOnSelect
          getOptionLabel={(option) => option.template_name}
          value={selectedTerms}
          onChange={(event, newValue) => handleTermsChange(event, newValue)} // Pass the field name
          renderOption={(props, option, { selected }) => (
            <li {...props}>
              <CustomCheckbox style={{ marginRight: 8 }} checked={selected} />
              {option.template_name}
            </li>
          )}
          fullWidth
          renderInput={(params) => (
            <CustomTextField {...params} placeholder="Terms and Conditions" aria-label="Terms and Conditions" />
          )}
          />

                      {customerData && customerData.invited_at ? 
                      <Grid
                        item
                        xs={12}
                        mt={3}
                        style={{
                            display: "flex",
                            justifyContent: "center",
                            alignItems: "center",
                        }}
                    > <Button
                            variant="contained"
                            color="primary"
                            type="button"
                            style={{ marginRight: "10px" }}
                            onClick={handleUpdateWorkflow}
                        >
                            Update Workflow
                        </Button> 
                    </Grid> : "" }


          <Divider sx={{ width: '100%', my: 2, mt: 4 }} />

          
          <Grid item xs={12}>
                        <Typography variant="h5">Member History</Typography>
                    </Grid>

                    <Grid item md={4} xs={3}>
                        <Timeline
                            className="theme-timeline"
                            nonce={undefined}
                            onResize={undefined}
                            onResizeCapture={undefined}
                            sx={{
                                p: 0,
                                mb: "-40px",
                                [`& .${timelineOppositeContentClasses.root}`]: {
                                    flex: 0.5,
                                    paddingLeft: 0,
                                },
                            }}
                        >

                          {/* invite blocked or not */}
                          {customerData && customerData.blocked_at ? (
                                <TimelineItem sx={{ marginBottom: "12px" }}>
                                    <TimelineOppositeContent>
                                        {customerData.blocked_at.split(" ")[0]}
                                        <br />
                                        {
                                            customerData.blocked_at.split(
                                                " "
                                            )[1]
                                        }{" "}
                                        {customerData.blocked_at.split(" ")[2]}
                                    </TimelineOppositeContent>
                                    <TimelineSeparator>
                                        <TimelineDot
                                            color="error"
                                            variant="outlined"
                                        />
                                        <TimelineConnector />
                                    </TimelineSeparator>
                                    <TimelineContent>
                                        <Typography fontWeight="600">
                                            User Blocked
                                        </Typography>{" "}
                                    </TimelineContent>
                                </TimelineItem>
                            ) : (
                                ""
                            )}

                            {/* invite accepted or not */}
                            {customerData && customerData.invite_accepted_at ? (
                                <TimelineItem sx={{ marginBottom: "12px" }}>
                                    <TimelineOppositeContent>
                                        {
                                            customerData.invite_accepted_at.split(
                                                " "
                                            )[0]
                                        }
                                        <br />
                                        {
                                            customerData.invite_accepted_at.split(
                                                " "
                                            )[1]
                                        }{" "}
                                        {
                                            customerData.invite_accepted_at.split(
                                                " "
                                            )[2]
                                        }
                                    </TimelineOppositeContent>
                                    <TimelineSeparator>
                                        <TimelineDot
                                            color="success"
                                            variant="outlined"
                                        />
                                        <TimelineConnector />
                                    </TimelineSeparator>
                                    <TimelineContent>
                                        <Typography fontWeight="600">
                                            Invitation Accepted
                                        </Typography>{" "} 
                                    </TimelineContent>
                                </TimelineItem>
                            ) : (
                                customerData && customerData.invited_at ? 
                                <TimelineItem sx={{ marginBottom: "12px" }}>
                                    <TimelineOppositeContent></TimelineOppositeContent>
                                    <TimelineSeparator>
                                        <TimelineDot
                                            color="warning"
                                            variant="outlined"
                                        />
                                        <TimelineConnector />
                                    </TimelineSeparator>
                                    <TimelineContent>
                                        <Typography fontWeight="600">
                                            Invitation Not Accepted
                                        </Typography>{" "}
                                    </TimelineContent>
                                </TimelineItem> : ""
                            )}

                            {/* invite sent or not sent */}
                            {customerData && customerData.invited_at ? (
                                <TimelineItem sx={{ marginBottom: "12px" }}>
                                    <TimelineOppositeContent>
                                        {customerData.invited_at.split(" ")[0]}
                                        <br />
                                        {
                                            customerData.invited_at.split(
                                                " "
                                            )[1]
                                        }{" "}
                                        {customerData.invited_at.split(" ")[2]}
                                    </TimelineOppositeContent>
                                    <TimelineSeparator>
                                        <TimelineDot
                                            color="primary"
                                            variant="outlined"
                                        />
                                        <TimelineConnector />
                                    </TimelineSeparator>
                                    <TimelineContent>
                                        <Typography fontWeight="600">
                                            Invitation Sent
                                        </Typography>{" "}
                                    </TimelineContent>
                                </TimelineItem>
                            ) : (
                                <TimelineItem sx={{ marginBottom: "12px" }}>
                                    <TimelineOppositeContent></TimelineOppositeContent>
                                    <TimelineSeparator>
                                        <TimelineDot
                                            color="secondary"
                                            variant="outlined"
                                        />
                                        <TimelineConnector />
                                    </TimelineSeparator>
                                    <TimelineContent>
                                        <Typography fontWeight="600">
                                            Invitation Not Sent
                                        </Typography>{" "}
                                    </TimelineContent>
                                </TimelineItem>
                            )}

                        </Timeline>
                    </Grid>

                    <Grid item xs={3} sx={{ marginTop: "20px" }}>
                        {customerData && customerData.blocked_at ? (
                            <Chip
                                sx={{
                                    bgcolor: (theme) =>
                                        theme.palette.error.light,
                                    color: (theme) => theme.palette.error.main,
                                    borderRadius: "6px",
                                    width: 150,
                                }}
                                size="medium"
                                label="User Blocked"
                            />
                        ) : customerData && customerData.invite_accepted_at ? (
                            <Chip
                                sx={{
                                    bgcolor: (theme) =>
                                        theme.palette.success.light,
                                    color: (theme) =>
                                        theme.palette.success.main,
                                    borderRadius: "6px",
                                    width: 150,
                                }}
                                size="medium"
                                label="User Active"
                            />
                        ) : (
                            <Chip
                                sx={{
                                    bgcolor: (theme) =>
                                        theme.palette.primary.light,
                                    color: (theme) =>
                                        theme.palette.primary.main,
                                    borderRadius: "6px",
                                    width: 150,
                                }}
                                size="medium"
                                label="User Inactive"
                            />
                        )}
                    </Grid>

                    <Grid
                        item
                        xs={12}
                        mt={10}
                        style={{
                            display: "flex",
                            justifyContent: "center",
                            alignItems: "center",
                        }}
                    >
                        {customerData && customerData.invite_accepted_at ? ("") : 
                        (
                            customerData && customerData.invited_at ? 
                            <Button
                                variant="contained"
                                color="success"
                                type="button"
                                style={{ marginRight: "10px" }}
                                onClick={handleSendInvite}
                            >
                                Resend Invite
                            </Button> :
                            <Button
                            variant="contained"
                            color="success"
                            type="button"
                            style={{ marginRight: "10px" }}
                            onClick={handleSendInvite}
                        >
                            Send Invite
                        </Button> 
                        )}
                        <Link to="/admin/customer-list">
                            <Button color="warning" variant="contained">
                                Back
                            </Button>
                        </Link>
                    </Grid>

        </Grid>
      
      </ParentCard>
    </PageContainer>
    </div>
  );
};

export default CustomerDetails;