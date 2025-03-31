import React, { useEffect, useState } from "react";
import Breadcrumb from '@src/layouts/full/shared/breadcrumb/Breadcrumb2';
import PageContainer from '@src/components/container/PageContainer';
import CommonTableList from '@src/common/CommonTableList';
import BlankCard from '@src/components/shared/BlankCard';
import axios from "axios";
import WorkflowSettings from './WorkflowSettings';
import { Alert, AlertTitle, Modal, Button, Box } from '@mui/material';
import Snackbar from "@mui/material/Snackbar";
import { format } from "date-fns";

const BCrumb = [
  {
    to: '/admin/dashboard',
    title: 'Home',
  },
  {
    title: 'Customer List',
  },
];

const CustomerList = () => {

  const [isSuccessVisible, setIsSuccessVisible] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [isErrorVisible, setIsErrorVisible] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [selectedCustomerId, setSelectedCustomerId] = useState(null);
  const [isWorkflowSettingsModalOpen, setIsWorkflowSettingsModalOpen] = useState(false);

  //const [perPage, setPerPage] = useState('');
  const [page, setPage] = useState('');
  const [totalCount, setTotalCount] = useState('');
  const [pageSortOrder, setpageSortOrder] = useState('');
  const [keyword, setkeyword] = useState('');
  const [loading, setLoading] = useState(true);
  const [isLoading, setIsLoading] = useState(false);
  const [totalPages, settotalPages] = useState([]); // State to store the fetched data
  //let page = '';
  let perPage = '';
  const headCells = [
    {
      id: 'id',
      numeric: false,
      disablePadding: false,
      label: 'Sr. No.',
      enableSorting:false,
    },
    {
      id: 'user_code',
      numeric: false,
      disablePadding: false,
      label: 'Customer ID',
      enableSorting:true,
    },
    {
      id: 'account_type',
      numeric: false,
      disablePadding: false,
      label: 'Account Type',
      enableSorting:true,
    },
    {
      id: 'name',
      numeric: false,
      disablePadding: false,
      label: 'Company Name',
      enableSorting:true,
    },
    {
      id: 'contact_person_number',
      numeric: false,
      disablePadding: false,
      label: 'Contact Number',
      enableSorting:true,
    },
    {
      id: 'email_id',
      numeric: false,
      disablePadding: false,
      label: 'Company Email ID',
      enableSorting:true,
    },
    {
      id: 'status',
      numeric: false,
      disablePadding: false,
      label: 'Status',
      enableSorting:true,
    },
    {
      id: 'actions',
      numeric: false,
      disablePadding: false,
      label: 'Actions',
      enableSorting:false,
    },
  ];

const dataColumns = [
  'srno',
  'user_code',
  'customer',
  'name',
  'contact_number_with_code',
  'email_id',
  'status',
];

const rowSettings = { 
  'user_code': {
    link_url: '/admin/customer-details/',
  },
  'customer' : {value : ['customer','account_type'] },
  'contact_number_with_code' : {value : ['customer','contact_person_number_with_code'] },
  status: {
    link_url: '/admin/customer-details/',
    sxstyle: {
      "Active": {
        bgcolor: (theme) => theme.palette.success.light,
        color: (theme) => theme.palette.success.main,
        borderRadius: '6px',
        },
        "Inactive": {
          bgcolor: (theme) => theme.palette.error.light,
          color: (theme) => theme.palette.error.main,
          borderRadius: '6px',
        },
    },
},
};

  //show = 1 for show the button show = 0 then button not show
  const actionSettings = { 
    'actions': {'edit' : {'url' : '', 'show':'1'},'delete': {'url':'', 'show':'0'},'preview': {'url':'/admin/customer-details/'},'invite': {}},
  };
  const headerButtons = {
    'syncdata' : {title:'Sync Data',onclick: 'handleExport', color: 'success', pageType: 'customer'},
    'test' : {title:'Test Sync Data',url: 'http://google.com', color: 'success'}
  }

  const addUrl='';
  const [datas, setData] = useState([]); // State to store the fetched data
  const appUrl = import.meta.env.VITE_API_URL;

  useEffect(() => {
    //when the component mounts
    fetchData();
    const storedSuccessMessage = sessionStorage.getItem('successMessage');

    if (storedSuccessMessage) {
      setIsSuccessVisible(true);
      setSuccessMessage(storedSuccessMessage);
      sessionStorage.removeItem('successMessage');
    }
  }, [setData]);

  // Function to fetch data from the API
  const fetchData = async (perPage='', page='', sortBy = "",search="",IsAsc="") => {
    var sortData = (sortBy == "") ? "id" : sortBy;
    var IsSort = (IsAsc == "") ? "asc" : IsAsc;
    setpageSortOrder(IsSort);
    if(page == ''){
      const savedPage = localStorage.getItem('tablePaginationPage');
      const currentURL = localStorage.getItem('currentURL');
      page = currentURL !== window.location.href ? '1' : (parseInt(savedPage, 10) > 0 ? savedPage : '1');
    }
    console.log('page : ', page);
    if(perPage == ''){
      const setPerPage = localStorage.getItem('rowsPerPage');
      const currentURL = localStorage.getItem('currentURL');
      perPage = (currentURL != window.location.href) ? 10 : setPerPage ? parseInt(setPerPage) : '10';
      sessionStorage.removeItem('searchKeyword');
    }
    try {
        const formData = new FormData();
        formData.append('page', page);
        formData.append("sortBy", sortData);
        formData.append('sortOrder', IsSort);
        formData.append('perPage', perPage);
        const searchKeyword = sessionStorage.getItem("searchKeyword");
        formData.append(
            "keyword",
            searchKeyword !== null ? searchKeyword : ""
        );

        const API_URL = appUrl + '/api/list-customer';
        const token = sessionStorage.getItem('authToken');
        const response = await axios.post(API_URL, formData, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        //console.log(JSON.stringify(response.data.data.data));
        if (response && response.data && response.data.data) {
          setData(response.data.data.data);
          settotalPages(response.data.data.last_page);
          setTotalCount(response.data.data.total);
        } else {
          setData(response.data);
          setTotalCount(0);
          settotalPages(1);
          console.error("Error: Unexpected response structure", response);
        }
    } catch (error) {
        console.error("Error fetching data:", error); // Log any errors
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (keyword1,rowsPerPage) => {
    fetchData(rowsPerPage);
  };

  //Handle the Export employee Data
  const handleExport = async (dateSyncDate = null) => {
    try {
      setLoading(true);
      setIsLoading(true);
      const API_URL = appUrl + '/api/import-customer-ad';
      const token = sessionStorage.getItem('authToken');
      const formData = new FormData();
      dateSyncDate ? formData.append('dt', dateSyncDate ? format(dateSyncDate, 'ddMMyyyy') : '') : '';
      const response = await axios.post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      setIsSuccessVisible(true);
      setSuccessMessage(response.data.message);
      fetchData();
      //console.log("Success", response.data);
    } catch (error) {
      setIsErrorVisible(true);
      if (error.response && error.response.data && error.response.data.message) {
        setErrorMessage(error.response.data.message);
      } else {
        setErrorMessage('An error occurred while exporting from AD.');
      }
      //console.error("Error:", error);
    }finally {
      setLoading(false); // Hide loader
      setIsLoading(false);
    }
  };

  const [selectedValues, setSelectedValues] = useState({
    primaryCrm: '',
    secondaryCrm: '',
    escalation: '',
  });
  const [validationError, setValidationError] = useState(false);
  const [customerData, setCustomerData] = useState(null);

  
  const [selectedValue, setSelectedValue] = useState(null);
  const [selectedValueSecondary, setSelectedValueSecondary] = useState(null);
  const [selectedValueEscalation, setselectedValueEscalation] = useState(null);
  const [primaryCrmValue, setPrimaryCrmValue] = useState(null);
  const [secondaryCrmValue, setSecondaryCrmValue] = useState(null);
  const [escalationValue, setEscalationValue] = useState(null);
  const [primaryCrmError, setPrimaryCrmError] = useState('');
  const [secondaryCrmError, setSecondaryCrmError] = useState('');
  const [escalationError, setEscalationError] = useState('');

  const handleValueChange = (event, newValue, fieldName) => {
    // Assuming newValue is an item object with an 'id' property
    const selectedItemId = newValue ? newValue.id : null;
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
    //Check if the selected value is already selected in other fields
    if (
      (fieldName === 'primaryCrm' && selectedItemId === secondaryCrmValue) ||
      (fieldName === 'primaryCrm' && selectedItemId === escalationValue) ||
      (fieldName === 'secondaryCrm' && selectedItemId === primaryCrmValue) ||
      (fieldName === 'secondaryCrm' && selectedItemId === escalationValue) ||
      (fieldName === 'escalation' && selectedItemId === primaryCrmValue) ||
      (fieldName === 'escalation' && selectedItemId === secondaryCrmValue)
    ) {
    }
    else{
      setPrimaryCrmError('');
      setSecondaryCrmError('');
      setEscalationError('');
    }
    // Clear the error message if no duplicate is detected
    setErrorMessage('');
    setValidationError(false);
    // Create a copy of the selectedValues object
    const updatedSelectedValues = { ...selectedValues };
  
    // Update the selected field based on the p rovided fieldName
    updatedSelectedValues[fieldName] = selectedItemId;
  
    // Update the state with the updated selectedValues
    setSelectedValues(updatedSelectedValues);
  };

  // Function to handle changes in any Select component
  {/*const handleSelectChange = (id) => (event) => {
    setSelectedValues({
      ...selectedValues,
      [id]: event.target.value,
    });
  };*/}

  //Handle the delete function
  const handleInvite = async (id) => {
    setSelectedCustomerId(id);
    setPrimaryCrmError('');
    setSecondaryCrmError('');
    setEscalationError('');
    setIsWorkflowSettingsModalOpen(true);
    const apiUrl = `${appUrl}/api/show-customer/${id}`;
      try {
        const token = sessionStorage.getItem('authToken');
        const response = await axios.get(apiUrl, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });

        // Update the state with the fetched data
        setSelectedValues({
          primaryCrm: response.data.data.customer.primary_crm_user_id || '',
          secondaryCrm: response.data.data.customer.secondary_crm_user_id || '',
          escalation: response.data.data.customer.escalation_user_id || '',
        });
        //setCheckedTerms(response.data.data.customer.terms_condition_ids);
        
        setCustomerData(response.data.data);
        //console.log(response.data);
      } catch (error) {
        //setError(error.response.data.message);
        //console.log('Error',error.response.data.message);
      }
  };

  const handleCloseWorkflowSettingsModal = () => {
    setIsWorkflowSettingsModalOpen(false);
    setSelectedCustomerId(null);
  };

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

    const formData = new FormData();
    formData.append('user_id', selectedCustomerId);
    formData.append('primary_crm_user_id', selectedValues.primaryCrm);
    formData.append('secondary_crm_user_id', selectedValues.secondaryCrm);
    formData.append('escalation_user_id', selectedValues.escalation);

    const API_URL = `${appUrl}/api/edit-customer`;
    const token = sessionStorage.getItem('authToken');

    axios
      .post(API_URL, formData, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      })
      .then((response) => {
        setIsSuccessVisible(true);
        setSuccessMessage('Workflow set and invite sent successfully!!!');
        setIsWorkflowSettingsModalOpen(false);
        setSelectedCustomerId(null);
        handleInviteCustomer(selectedCustomerId);
        console.log('Edit customer response:', response.data);
      })
      .catch((error) => {
        setValidationError(true);
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
  
 //For Download Excel variable
 const excelName='customer';
 const excelApiUrl='export-customer';

  return (
    <PageContainer title="Customer List" description="this is Customer List page">
      <Breadcrumb title="" />

      <Snackbar
                anchorOrigin={{ vertical: "top", horizontal: "right" }}
                open={isErrorVisible}
                autoHideDuration={8000}
                onClose={() => setIsErrorVisible(false)}
            >
                <Alert severity="error">
                    <div style={{ fontSize: "17px", padding: "10px" }}>
                        {errorMessage && <div>{errorMessage}</div>}
                    </div>
                </Alert>
            </Snackbar>
            <Snackbar
                anchorOrigin={{ vertical: "top", horizontal: "right" }}
                open={isSuccessVisible}
                autoHideDuration={8000}
                onClose={() => setIsSuccessVisible(false)}
            >
                <Alert severity="success">
                    <div style={{ fontSize: "17px", padding: "10px" }}>
                        {successMessage && <div>{successMessage}</div>}
                    </div>
                </Alert>
            </Snackbar>

      <BlankCard>
        {/* ------------------------------------------- */}
        {/* Left part */}
        {/* ------------------------------------------- */}
        <CommonTableList pageTitle = {"Customer List"} headCells = {headCells} dataRow = {datas} page={page} dataColumns = {dataColumns}
         handleSearch = {handleSearch} handleInvite = {handleInvite} handleExport={handleExport} rowSettings = {rowSettings}
         actionSettings={actionSettings} fetchData={fetchData} totalCount={totalCount} addUrl={addUrl} excelName={excelName} excelApiUrl={excelApiUrl}
          headerButtons={headerButtons} loading = {loading} totalPage={totalPages} pageSortOrder={pageSortOrder} isLoading={isLoading}
        />

        <Modal
          open={isWorkflowSettingsModalOpen}
          onClose={handleCloseWorkflowSettingsModal}
          aria-labelledby="workflow-settings-modal"
          aria-describedby="workflow-settings-description"
        >
          <Box sx={{
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            width: '90%',
            maxWidth: '600px',
            bgcolor: 'background.paper',
            border: '',
            boxShadow: 24,
            p: 4,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
          }}>
            {validationError && (
            <Alert severity="error" onClose={() => setValidationError(false)} sx={{ mb: 2 }}>
              <AlertTitle>{errorMessage}</AlertTitle>
            </Alert>
          )}
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
            <div style={{ marginTop: '30px' }}>
              <Button 
                variant="contained"
                color="success"
                type="button"
                style={{ marginRight: '10px' }}
                onClick={handleSendInvite}
              >
                Send Invite
              </Button>
              <Button 
                variant="contained"
                color="warning"
                type="button"
                onClick={handleCloseWorkflowSettingsModal}
              >
                Close
              </Button>
            </div>
          </Box>
        </Modal>
      </BlankCard>
    </PageContainer>
  );
};

export default CustomerList;
