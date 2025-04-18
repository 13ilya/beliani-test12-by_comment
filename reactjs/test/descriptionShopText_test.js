//descriptionTextShop_test.js
import {assert} from 'chai'
import func from '../frontend/reducers/SA/descriptionTextShop'

describe('Reduce descriptionTextShop.js testing ',()=>{
	it('FETCH_DESCRIPTION_TEXT_SHOP',()=>{
		const state = undefined
		const data = {data:{top_left_banner_color:'ghbdtn'}};
		const action = {type:'FETCH_DESCRIPTION_TEXT_SHOP', data}
		let newState = func(state,action);
		assert.deepEqual(newState.data,{top_left_banner_color:'ghbdtn'},'Not equal data in object');
	});
	it('DESCRIPTION_TEXT_SHOP_CHANGE_MULANGFIELD',()=>{
		const state = {description_shop_text_1:{english:{value:''}}};
		const name = 'description_shop_text_1';
		const title_lang = 'english';
		const value = 'newValue';
		const action = {type:'DESCRIPTION_TEXT_SHOP_CHANGE_MULANGFIELD', name,value,title_lang}
		const newState = func(state,action);
		assert.deepEqual(newState,{description_shop_text_1:{english:{value:'newValue'}}},'Not equal data in object');
	});
});


