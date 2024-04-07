@extends('layouts.app')
 
@section('title', 'My Wallet')
 
@section('content')
  <div class="flex flex-col w-full gap-3">
    @foreach ($transactions as $transaction)
      <div class="border border-blue p-3" >
        <div>Txn Hash: {{ $transaction->tx_id }}</div>
        <div>Type: {{ $transaction->type }}</div>
        <div>Token: {{ $transaction->trx }} TRX</div>
        <div>Time: {{ $transaction->time }}</div>
        <div>DateTime: {{ $transaction->datetime }}</div>
      </div>
    @endforeach
    @if (isset($transactionMeta['fingerprint']))
      <a href="{{ route('wallet.detail', [ 'address' => request()->route('address'), 'fingerprint' => $transactionMeta['fingerprint'] ] ) }}" class="h-6 mr-2">
        下一頁
      </a>
    @endif
  </div>
@endsection

@section('script')

<script>
function copyTextToClipboard(address) {
  // 创建一个临时的textarea元素
  var textarea = document.createElement("textarea");
  textarea.value = address;

  // 将textarea元素添加到文档中
  document.body.appendChild(textarea);

  // 选择textarea中的文本
  textarea.select();

  // 复制文本到剪贴板
  document.execCommand("copy");

  // 移除textarea元素
  document.body.removeChild(textarea);
}
</script>

@endsection