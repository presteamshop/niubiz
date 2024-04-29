{if isset($logNbz) && $logNbz != null}

  <div class="card mt-2" data-role="message-card">
      <div class="card-header">
          <div class="row">
              <div class="col-md-6">
                  <h3 class="card-header-title">
                  Niubiz Log
                  </h3>
              </div>
          </div>
      </div>

      <div class="card-body d-print-none">
          
          <img src="{$logoNiubiz}">
          
          <table class="table table-bordered">
              <tr>
                  <th>Registro</th>
                  <th>Valor</th>
                </tr>
                
              {if $logNbz.id}
              <tr>
                  <td>id_log</td>
                  <td>{$logNbz.id}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_order}
              <tr>
                  <td>id_order</td>
                  <td>{$logNbz.id_order}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_cart}
              <tr>
                  <td>id_cart</td>
                  <td>{$logNbz.id_cart}</td>
              </tr>
              {/if}
              
              {if $logNbz.id_customer}
              <tr>
                  <td>id_customer</td>
                  <td>{$logNbz.id_customer}</td>
              </tr>
              {/if}
              
              {if $logNbz.dsc_cod_accion}
              <tr>
                  <td>Cod Action</td>
                  <td>{$logNbz.dsc_cod_accion}</td>
              </tr>
              {/if}
              
              {if $logNbz.transactiontoken}
              <tr>
                  <td>transactiontoken</td>
                  <td>{$logNbz.transactiontoken}
                  <button onclick="copyToClipboard('{$logNbz.transactiontoken}')" class="btn btn-sm btn-default"> Copiar </button></td>
              </tr>
              {/if}
              
              {if $logNbz.pan}
              <tr>
                  <td>PAN de tarjeta</td>
                  <td>{$logNbz.pan}</td>
              </tr>
              {/if}
              
               {if $logNbz.numorden}
              <tr>
                  <td>Numero de pedido</td>
                  <td>{$logNbz.numorden}</td>
              </tr>
              {/if}
              
               {if $logNbz.dsc_eci}
              <tr>
                  <td>Tipo</td>
                  <td>{$logNbz.dsc_eci}</td>
              </tr>
              {/if}
              
               {if $logNbz.date_add}
              <tr>
                  <td>Fecha</td>
                  <td>{$logNbz.date_add}</td>
              </tr>
              {/if}
          
          </table>
      </div>
  </div>

  {literal}
  <script>
  function copyToClipboard(text) {
      if (text != undefined) {
          navigator.clipboard.writeText(text);
      }
  }
  </script>
  {/literal}
{/if}
