import React from 'react'
import PageContent from './PageContent'
import ReactSpinner_container from '../../helpers/ReactSpinner'
import Confirm from '../../helpers/Confirm'
import Alert from '../../helpers/Alert'
import Page_log from '../../helpers/page_log'
const Main = ({
    children
}) => (
    <div className='minimize'>
        <ReactSpinner_container />
        <Confirm/>
        <Alert/>
        <PageContent>
            {children}
        </PageContent>
        <Page_log
            width = '99%'
            url = {location.pathname + location.search + location.hash}/>
    </div>
);

export default Main
