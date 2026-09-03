<!DOCTYPE html>
<html lang="en">
<meta charset="utf-8">
<title>Print</title>
<link rel="stylesheet" type="text/css" href="{{ asset('css/print.css') }}" media="print" />
<link rel="stylesheet" type="text/css" href="{{ asset('css/print.css') }}" />
<body>
<table width="100%" border="0">
<tr>
<td>
<strong>{{ $transaction->invoice }}</strong><br/>
<table>
	@if($transaction->sender)
	<tr>
		<td><strong>Sender:</strong></td>
		<td>{{ $transaction->sender->getDetailLink() }}</td>
	</tr>
	@endif
	@if($transaction->receiver)
	<tr>
		<td><strong>Receiver:</strong></td>
		<td>{{ $transaction->receiver->getDetailLink() }}</td>
	</tr>
	@endif
</table>
</td>

<td>
	<table>
		<tbody>
			<tr><td>Date</td><td>{{ Dater::display($transaction->date) }}</td></tr>
			<tr><td>Discount</td><td>{{$transaction->discount}}%</td></tr>
			<tr><td>Adjustment</td><td>{{ number_format($transaction->adjustment) }}</td></tr>
			<tr><td>Total</td><td>{{ number_format($transaction->total) }}</td></tr>
			<tr><td>Items</td><td>{{ number_format($transaction->total_items) }}</td></tr>
		</tbody>
	</table>
</td>
</tr>
</table>

@if(isset($details))
<hr/>
<table width="100%">
	<thead><tr><th>Code</th><th>Quantity</th><th>Price</th><th>Discount(%)</th><th>Total</th></tr></thead>
	<tbody>
@foreach($details as $detail)
	<tr>
		<td>{{ HTML::link(URL::action('ItemsController@getDetail',array($detail->item_id)),$detail->item->name) }}</td>
		<td>{{ print_quantity($detail->quantity) }}</td>
		<td>{{ number_format($detail->price) }}</td>
		<td>{{ $detail->discount }}</td>
		<td>{{ number_format($detail->total) }}</td>
	</tr>
@endforeach
	</tbody>
</table>
@endif
<hr/>

<table width="100%">
<tr><td>Yang Mengetahui,</td><td>Pemberi,</td><td>Penerima,</td></tr>
</table>
</body>
</html>
