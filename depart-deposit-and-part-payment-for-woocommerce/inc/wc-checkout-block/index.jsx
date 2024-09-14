const { registerPlugin } = wp.plugins;
import { DepositBlock } from './src/cart-deposit-summary';

registerPlugin( 'depart-deposit-and-part-payment-for-woocommerce', {
	render: DepositBlock,
	scope: 'woocommerce-checkout',
} );
