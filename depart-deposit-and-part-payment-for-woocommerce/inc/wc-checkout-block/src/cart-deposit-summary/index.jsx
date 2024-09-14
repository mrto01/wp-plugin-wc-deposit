import {__} from '@wordpress/i18n';
import './style.scss';

const {ExperimentalOrderMeta} = wc.blocksCheckout;
const {FormattedMonetaryAmount} = wc.blocksComponents;
const currency = wc.priceFormat.getCurrency();
const DepositSummary = ({cart, extensions, context}) => {
	if ( extensions.hasOwnProperty('depositData') && extensions.depositData.future_payment > 0 && extensions.depositData.deposit_amount > 0 ) {
		const depositData = {};
		depositData.depositAmount = extensions.depositData.deposit_amount;
		depositData.futurePayment = extensions.depositData.future_payment;
		depositData.feeTotal = extensions.depositData.fee_total;
		depositData.depositFee = extensions.depositData.deposit_fee;
		depositData.showFees = extensions.depositData.show_fees;
		depositData.depositLabel = extensions.depositData.deposit_text;
		depositData.futurePaymentsLabel = extensions.depositData.future_payments_text;
		depositData.feesLabel = extensions.depositData.fees_text;
		currency.prefix = currency.symbol + " ";
		return (
			<div className="vi-block-deposit-wrapper">
				<div className="vi-block-deposit-item wc-block-components-totals-item">
					<span className="vi-block-deposit-item__label">{depositData.depositLabel}</span>
					<span className="vi-block-deposit-item__value">
						<FormattedMonetaryAmount
							currency={currency || {}}
							value={depositData.depositAmount}
						/>
						<> </><FeesOnCheckout depositData={depositData} context={'deposit'}/>
					</span>
				</div>
				<div className="vi-block-deposit-item wc-block-components-totals-item">
					<span
						className="vi-block-deposit-item__label">{depositData.futurePaymentsLabel}</span>
					<div className="vi-block-deposit-item__value">
						<FormattedMonetaryAmount
							currency={currency || {}}
							value={depositData.futurePayment}
						/>
						<> </><FeesOnCheckout depositData={depositData} context={'future_payment'}/>
					</div>
				</div>
			</div>
		);
	} else {
		return <></>;
	}
};

const FeesOnCheckout = ( {depositData, context} ) => {
	if ( depositData.showFees != 0 ) {
		currency.prefix = currency.symbol + " ";
		let feeAmount = 0;
		if ( context === 'deposit') {
			feeAmount = depositData.depositFee;
		}else{
			feeAmount = depositData.feeTotal - depositData.depositFee;
		}
		if( feeAmount > 0 ) {
			return (
				<>
					<small className="vi-block-fee">
						(+ <FormattedMonetaryAmount currency={currency || {} } value={feeAmount}/> {depositData.feesLabel})
					</small>
				</>
			)
		}
	}else {
		return <></>
	}
}

export const DepositBlock = () => {
	return (
		<ExperimentalOrderMeta>
			<DepositSummary/>
		</ExperimentalOrderMeta>
	);
};
