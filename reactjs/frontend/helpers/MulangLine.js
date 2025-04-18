import React from 'react'

export default React.CreateClass({
	render:(){
	const{
		line
	}=this.props;
	if(line==undefined)return false;
	function inputHtml(){
		<div className='form-controll'>
						<h4>langs</h4>
						<div className='col-md-8'>
							<input className='form-control' type='text'/>
							<p className='small'>Was changed by {last_by1} on {last_on1}</p>
						</div>
						<div className='col-md-4'>
							<input className='form-control' type='text'/>
							<p className='small'>Was changed by {last_by1} on {last_on1}</p>
						</div>
                    </div>)
	}


		return:{
		<div className={className}>
            <label htmlFor='exampleInputName2'>{title_lang}</label>
            {inputHtml()}
        </div>
		}
	}
});

