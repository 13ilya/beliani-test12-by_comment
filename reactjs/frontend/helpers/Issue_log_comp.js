import React from 'react';
import CheckGroup from './checkGroup'

export class Types_block extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    const {
        issue_types = [],
        values = [],
        fld = '',
        title = '',
        func,
        disabled
    } = this.props
    return (
        <div>
            <div className='col-md-12'>{title}</div>
            {issue_types.map((type,idx)=>{
                return(
                    <div
                        key = {idx+'issue_types'}
                        className = 'col-md-3 col-xs-12'
                        onClick = {()=>{
                            var index = values.indexOf(type.value),
                                res = values;
                            if(index == -1){
                                res.push(type.value);
                            }else{
                                res.splice(index,1);
                            }

                            if(func){
                                func(res);
                            }else{
                                this.props.generateSimpleAction(
                                    'CUSTOM_DATA_CHANGE',
                                    'new_fields',
                                    fld,
                                    res
                                )
                            }
                        }}
                        >
                        <CheckGroup
                            iValue = {values.indexOf(String(type.value))!=-1}
                            value = {type.value}
                            label = {type.label}
                            disabled = {disabled}
                            func = {() => {}}
                        />
                    </div>
                )
            })}
        </div>
    );
  }
}
