//banner_test.js
import {assert} from 'chai'
import Banner from '../frontend/reducers/SA/Banner'

describe('Reduce Banner.js testing',()=>{
	it('FETCH_BANNER',()=>{
		const state = undefined
		const data = {data:{top_left_banner_color:'ghbdtn'}};
		const action = {type:'FETCH_BANNER', data}
		let newState = Banner(state,action);
		assert.deepEqual(newState.data,{top_left_banner_color:'ghbdtn'},'Not equal data in object');
	});
	it('BANNER_INPUT_CHANGE',()=>{
		//const state = {top_left_banner_color:'ghbdtn'}
		const state = {data:{top_left_banner_color:'ghbdtn'}};
		const field_type = 'top_left_banner_color';
		const value = 'newValue';
		const action = {type:'BANNER_INPUT_CHANGE', field_type,value}
		let newState = Banner(state,action);
		assert.deepEqual(newState.data,{top_left_banner_color:'newValue'},'Not equal data in object');
	});
	it('BANNER_CHANGE_MULANGFIELD',()=>{
		//const state = {top_left_banner_color:'ghbdtn'}
		const state = {
			data:{
				top_left_banner_size:{
					english:{
						value:''
					}
		}}};
		//const func_type = 'top_left_banner_color';
		const field_type = 'top_left_banner_size';
		const title_lang = 'english';
		const value = 'newValue';
		const action = {type:'BANNER_CHANGE_MULANGFIELD', field_type,value,title_lang}
		const newState = Banner(state,action);
		assert.deepEqual(newState.data,{top_left_banner_size:{english:{value:'newValue'}}},'Not equal data in object');
	});
});


