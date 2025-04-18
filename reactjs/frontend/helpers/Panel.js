import React from 'react'

const Panel = ({
    title,
    id,
    children,
    ClassBox
}) => {
    return (
        <div id={id} className={ClassBox==undefined?'panel panel-default':ClassBox}>
            <div className='panel-heading'>{title}</div>
            <div className='panel-body'>
                {children}
            </div>
        </div>
    )
};

export default Panel
