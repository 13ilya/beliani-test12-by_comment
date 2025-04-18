//itemGroup.jsx
import React from 'react'
export default class ItemGroup extends React.Component {
    render() {
        const {
            iValue = '',
            label = '',
            bsClass = '',
            func,
            type = 'text',
            btn_func,
            btn_label = '',
            disabled = false,
            placeholder = 'Insert text here....',
        } = this.props;
        return (
            <div className={'input-group '+bsClass}>
                <span className='input-group-addon'>{label}</span>
                <input
                    type={type}
                    className='form-control'
                    disabled={disabled}
                    placeholder={placeholder}
                    value={iValue}
                    onChange={(e)=>{func(e.target.value)}}/>
                {btn_func?
                    <span className='input-group-btn'>
                        <input
                            type={'button'}
                            className='def_btn'
                            value={btn_label}
                            onClick={ btn_func }/>
                    </span>:''
                }
            </div>
        );
    }
}
