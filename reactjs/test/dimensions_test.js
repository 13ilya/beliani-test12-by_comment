//dimensions_test.js
import {assert} from 'chai'
import func from '../frontend/reducers/SA/Dimensions'
const initialState = {
            'options': {
                'parcels': {
					'count_new': 0,
                    'weight_kg_carton': 1,
                    'weight_g_carton': 2,
                    'volume_m3_carton': 3
                },
                'real_parcels': {
					'count_new': 0,
                    'shipping_art': 'Paketversand',
                    'weight_kg_carton': 1,
                    'weight_g_carton': 1,
                    'volume_m3_carton': 1
                }
            },
            'data': {
				'parcels':{},
				'real_parcels':{},
				'old': {},
				'otherOpt': {}
			}
}

describe('Reduce descriptionShop.js testing',()=>{
	it('FETCH_DIMENSIONS',()=>{
		const state = undefined;
		const dimensions = initialState;
		const action = {type:'FETCH_DIMENSIONS', dimensions}
		let newState = func(state,action);
		assert.deepEqual(newState,initialState,'Not equal data in object');
	});
});


