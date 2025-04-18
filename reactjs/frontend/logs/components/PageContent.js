import React from 'react'
const PageContent = ({
    children
}) => {
    return (
        <div style={{width:'99%',margin:'0px auto'}}>
            {children}
            <div className='clearfix'></div>
        </div>
    )
};

export default PageContent
