//reducer_test.js
import {expect} from 'chai'
import AmazonPars from '../frontend/reducers/SA/AmazonPars'

describe('Reduce AmazonPars.js  testing',()=>{
	it('FETCH_AMAZON_PARS',()=>{
		const state = undefined;
		const data = {data:30};
		const action = {type:'FETCH_AMAZON_PARS', data}

		let newState = AmazonPars(state,action);

		expect(newState.data).to.equal(30);
		expect(state).to.equal(undefined);
	});
	it('AMAZON_PARS_BP_CHANGE_MULANGFIELD',()=>{
		const initialState = {
			amazon_bp: {
				"amazon_bp_1": {
				"polish": {
					"language": "polish",
					"table_name": "sa",
					"field_name": "amazon_bp_1",
					"id": "532",
					"value": "",
					"iid": "12189329",
					"unchecked": "1",
					"updated": "0",
					"last_on": "2013-02-27 10:16:31",
					"last_by": "Marta Guenther"
					}
				}
			},
			amazon_st: {}
		};
		const action = {type:'AMAZON_PARS_BP_CHANGE_MULANGFIELD', value:'hi', name:'amazon_bp_1', title_lang:'polish'}

		let newState = AmazonPars(initialState,action);

		expect(newState.amazon_bp.amazon_bp_1.polish.value).to.equal('hi');
		//expect(state).to.equal(undefined);
	});
	it('AMAZON_PARS_ST_CHANGE_MULANGFIELD',()=>{
		const initialState = {
			amazon_bp: {
				"amazon_bp_1": {
				"polish": {
					"language": "polish",
					"table_name": "sa",
					"field_name": "amazon_bp_1",
					"id": "532",
					"value": "",
					"iid": "12189329",
					"unchecked": "1",
					"updated": "0",
					"last_on": "2013-02-27 10:16:31",
					"last_by": "Marta Guenther"
					}
				}
			},
			amazon_st: {
			"amazon_st_2": {
				"english": {
					"language": "english",
					"table_name": "sa",
					"field_name": "amazon_st_2",
					"id": "532",
					"value": "",
					"iid": "12189345",
					"unchecked": "1",
					"updated": "0",
					"last_on": "2013-02-27 10:16:31",
					"last_by": "Marta Guenther"
				}
			}}
		};
		const action = {type:'AMAZON_PARS_ST_CHANGE_MULANGFIELD', value:'hi', name:'amazon_st_2', title_lang:'english'}

		let newState = AmazonPars(initialState,action);

		expect(newState.amazon_st.amazon_st_2.english.value).to.equal('hi');
		//expect(state).to.equal(undefined);
	});
});


