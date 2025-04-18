import React from 'react'

const PanelCollapsible = ({
    title = '',
    id = '',
    children,
    colapsed=false,
    mobile_link_class = '',
    onColapsed='',
    notSaved=false,
    link_id,
    clearData,
    label,
    additional
}) => {

    let format = label || title,
        titleFormat = format.replace(/[.\s]+/g, '');
    return (
        <div id={id} className='panel-group' role='tablist'>
            <div className='panel panel-default'>
                <div className='panel-heading' role='tab' id={'collapseHeading'+titleFormat}>
                    <h4 className='panel-title'>
                        <a role='button'
                            data-toggle='collapse'
                            id={link_id}
                            href={notSaved?'':'#collapseContent'+titleFormat}
                            aria-expanded={colapsed}
                            aria-controls={'#collapseContent'+titleFormat}
                            className={mobile_link_class +' '+(colapsed?'':'collapsed')}
                            onClick={(e)=>{
                                if (!notSaved && $(e.target).attr('aria-expanded') != 'false'){
                                    onColapsed != '' ? onColapsed( id ) : false;
                                }
                                if(notSaved)
                                    if (confirm('The data was not saved, are you sure want to continue?')){
                                        $(e.target).attr('aria-expanded',false);
                                        $(e.target).attr('Class','');
                                        $('div#collapseContent'+titleFormat).attr('class','panel-collapse collapse');
                                        clearData? clearData():false;
                                    }
                                }
                            }
                            >
                            {title}
                        </a>
                        {additional? additional : ''}
                    </h4>
                </div>
                <div id={'collapseContent'+titleFormat} className={colapsed?'panel-collapse collapse in':'panel-collapse collapse'} role='tabpanel' aria-labelledby={'#collapseHeading'+titleFormat}>
                    <div className='panel-body'>
                        {children}
                    </div>
                </div>
            </div>
        </div>

    )
};

export default PanelCollapsible
